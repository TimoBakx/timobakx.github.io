---
layout: post
title:  "An MCP server in Symfony, with OAuth on both sides"
tagline: "Teaching Claude to query an internal dashboard, without opening that dashboard up to the world"
date:   2026-07-20 18:00:00 +0200
categories: php symfony
excerpt: "How I added MCP tools to an internal Symfony dashboard, and what it takes to let Claude in: discovery, dynamic client registration, PKCE, and the Google login that was already there."
thanks:
---

I work at a web agency. That means that most developers work on multiple projects at any given time. It
can be a challenge to keep track of what runs where, what's using which version of PHP and packages and
which projects need upgrading. So we built an internal dashboard that tracks the health of our projects.
It is a plain Symfony application, and it is strictly an internal tool.

Because our organisation runs on Google Workspace, authentication was the easy part: sign in with
Google, bound to the company itself. The [HWI OAuth bundle][_hwi] handles the client side of that
conversation.

Then I started asking myself questions like "which projects are affected by this new CVE?" and
realised I didn't want to click through the dashboard to answer them. I wanted to ask Claude, and I
wanted Claude to ask the dashboard. That is exactly what the [Model Context Protocol][_mcp] (MCP) is
for: it gives an AI client a standard way to discover and call tools that you define, with typed
parameters and structured results.

## Writing the MCP tools

The [MCP bundle][_mcp_bundle] from the [Symfony AI project][_symfony_ai] does most of the heavy lifting.

The server itself is configuration:

```yaml
mcp:
    app: 'Dashboard'
    description: 'Project-health data: Composer package usage, outdated packages, security advisories, ...'
    instructions: |
        Start broad with get-overview (portfolio counts) or list-advisories (every CVE and the
        projects it affects), then drill in with list-projects, list-packages, and get-project.

    client_transports:
        stdio: true # This lets AI connect to your MCP server through the terminal, perfect for developing & debugging
        http: true # This lets AI connect to your MCP server over http, we'll use that in production

    http:
        path: /mcp
```


Each tool is an invokable class with an attribute:

```php
#[McpTool(
    name: 'list-projects',
    description: 'Find registered projects, optionally narrowed by a search term, sync status, '
        . 'whether they have outdated packages, the severity of their known security advisories, '
        . 'or their stack versions (PHP, Symfony, API Platform).',
    annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
)]
final readonly class ListProjects
{
    public function __construct(
        private Store $store,
    ) {
    }

    /**
     * @param string|null $search case-insensitive match on project name or repository name
     * @param bool|null $outdated true = only projects with outdated packages, false = only up-to-date, omit for all
     * @param SeverityFilter|null $advisorySeverity only projects with an advisory of this severity, omit for all
     * @param string|null $symfony only projects on this Symfony version — a full version or a prefix like "6.4"
     */
    public function __invoke(
        ?string $search = null,
        ?bool $outdated = null,
        ?SeverityFilter $advisorySeverity = null,
        ?string $symfony = null,
        #[Schema(minimum: 1)]
        int $page = 1,
        #[Schema(minimum: 1, maximum: 100)]
        int $itemsPerPage = 30,
    ): array {
        // query the database, return plain arrays
    }
}
```

The JSON schema that MCP clients need is derived from the PHP signature. Parameter types become
schema types, docblock descriptions become parameter descriptions, a backed enum like
`SeverityFilter` becomes an enum in the schema, and the `#[Schema]` attribute adds constraints the
type system cannot express. Regular dependency injection works, since the tool is just a service.

So in this project, I end up with entry points into the system that look very familiar. MCP tools
for AI, and controllers for API endpoints and web views. Each of them is an invokable service with
as little domain logic as possible. Just a thin layer between the input, the rest of the application,
and the output.

I started with the STDIO transport: a local client starts `bin/console mcp:server` as a subprocess,
and that worked almost immediately. It is also useless for claude.ai, or for any colleague who is
not sitting at my machine. For that, I needed the HTTP transport, which means there is now a `/mcp`
endpoint on a domain.

## The problem with the HTTP transport

The STDIO transport inherits my local user and needs no authentication. I just assume that anyone
that has access to my laptop is a verified employee of our company. The HTTP transport is a URL
on the internet. This dashboard knows every outdated package and open CVE across our projects, so
"no authentication" is not a good option.

There are a few obvious ways to protect an HTTP endpoint:

- **A static token in a header**, a shared secret with no identity attached: I cannot tell
  users apart, I cannot revoke one person's access, and every rotation means updating
  everyone's configuration by hand.
- **A VPN or an IP allowlist**, claude.ai talks to MCP servers from Anthropic's infrastructure, not
  from my machine, so keeping the endpoint internal would lock out exactly the client I was
  building this for.
- **Basic auth**, we have multiple users now, but we'll have to manage them and their passwords.
  And the configuration on the Claude side is manual.
- **OAuth 2**, this is what we'll need. I've seen this work in other official MCPs (Atlassian, Gmail,
  etc.) and it sounds like it would solve all our problems.

OAuth expects the MCP endpoint to behave as an OAuth 2 *resource server*, and it expects the client to
find everything it needs by itself:

1. The client calls the MCP endpoint and gets a `401`, which points to metadata.
2. The metadata names the *authorization server* that can issue tokens.
3. The client registers **itself** as an OAuth client, using Dynamic Client Registration.
4. The human approves via the normal authorization-code flow (with PKCE, since the client is public).
5. The client calls the MCP endpoint again, now with a Bearer token, and refreshes silently from then on.

No pre-shared secrets, no manually configured client IDs. Which is elegant, and also means my
dashboard had to become an OAuth authorization server with an open registration endpoint.

## Becoming an OAuth server

The [league/oauth2-server-bundle][_league] provides the core: an `/authorize` endpoint, a `/token`
endpoint, token storage through Doctrine, and a `security.yaml` authenticator for validating access
tokens on the resource side. The configuration disables everything MCP does not need:

```yaml
league_oauth2_server:
    authorization_server:
        enable_auth_code_grant: true
        enable_refresh_token_grant: true
        enable_client_credentials_grant: false
        enable_password_grant: false
        enable_implicit_grant: false
        require_code_challenge_for_public_clients: true
    scopes:
        available: ['mcp']
        default: ['mcp']
```

What the bundle does *not* provide is everything around discovery and registration. That turned out
to be about 280 lines of code in total, spread over a handful of small classes.

### The 401 that starts it all

Symfony's security component lets a firewall define an *entry point*: the response for a request that
is not authenticated. The default one for the OAuth2 authenticator sends a bare
`WWW-Authenticate: Bearer`, which is correct, and also useless. The MCP discovery chain starts with a
pointer to the protected-resource metadata ([RFC 9728][_rfc9728]):

```php
public function start(Request $request, ?AuthenticationException $authException = null): Response
{
    $metadata = $this->urlGenerator->generate(
        'oauth.metadata.protected_resource', [], UrlGeneratorInterface::ABSOLUTE_URL,
    );

    return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED, [
        'WWW-Authenticate' => \sprintf('Bearer resource_metadata="%s"', $metadata),
    ]);
}
```

### The discovery documents

For this, I needed two small JSON controllers. The paths for these documents are not mine to
choose: both RFCs pin them to fixed "well-known" locations, so we need to copy those exactly.

The first controller serves the protected-resource metadata: it names the resource and points at the
authorization server, which is the same application in this case:

```php
#[Route('/.well-known/oauth-protected-resource', name: 'oauth.metadata.protected_resource', methods: ['GET'])]
#[Route('/.well-known/oauth-protected-resource/mcp', name: 'oauth.metadata.protected_resource_mcp', methods: ['GET'])]
final readonly class ProtectedResourceMetadata
{
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'resource' => $this->urlGenerator->generate('_mcp_endpoint', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'authorization_servers' => [$request->getSchemeAndHttpHost()],
            'scopes_supported' => ['mcp'],
            'bearer_methods_supported' => ['header'],
        ]);
    }
}
```

The `_mcp_endpoint` route name it points to is one supplied by the MCP bundle, which registers it for whatever
path is configured as the HTTP transport, so the metadata always points at the real endpoint.

The second controller serves the authorization-server metadata ([RFC 8414][_rfc8414]), listing the endpoints
and capabilities:

```php
#[Route('/.well-known/oauth-authorization-server', name: 'oauth.metadata.authorization_server', methods: ['GET'])]
final readonly class AuthorizationServerMetadata
{
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'issuer' => $request->getSchemeAndHttpHost(),
            'authorization_endpoint' => $this->urlGenerator->generate('oauth2_authorize', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'token_endpoint' => $this->urlGenerator->generate('oauth2_token', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'registration_endpoint' => $this->urlGenerator->generate('oauth.register', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp'],
        ]);
    }
}
```

The `oauth2_authorize` and `oauth2_token` route names are supplied by the OAuth server bundle;
`oauth.register` is my own, and its `registration_endpoint` line is what triggers the next step.

### Dynamic Client Registration, hardened

[RFC 7591][_rfc7591] registration is a public, unauthenticated POST endpoint that creates OAuth
clients. Typing that sentence still makes me slightly uncomfortable, so I hardened the endpoint in
three ways:

- **Rate limiting per IP**, through Symfony's rate limiter component. Registrations are rare; a
  burst of them is not a Claude client, it is a script.
- **Redirect URIs are pinned.** Every requested redirect URI must match a known, genuine Claude
  callback. Anything else is rejected outright.
- **Grants and scope are forced.** Whatever the registrant asks for, the stored client gets the
  authorization-code and refresh-token grants and the `mcp` scope. A registrant cannot request more.

The redirect URI allowlist is one small class:

```php
final class ClaudeRedirectUris
{
    /**
     * @var list<non-empty-string>
     */
    private const array PATTERNS = [
        '#^http://(localhost|127\.0\.0\.1):\d{1,5}/callback$#',       // Claude Code
        '#^http://(localhost|127\.0\.0\.1):\d{1,5}/oauth/callback$#', // Claude Desktop
        '#^https://claude\.ai/api/mcp/auth_callback$#',               // Claude.ai web
    ];

    public function allows(string $redirectUri): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (\preg_match($pattern, $redirectUri) === 1) {
                return true;
            }
        }

        return false;
    }
}
```

There is no configuration for this: it is a plain service, and autowiring injects it into the
registration endpoint and into the authorization listener from the next section, so both checks
share the same list.

The loopback clients get a free port choice (they open an ephemeral local port for the callback), but
host and path are fixed. The hosted web client is a single fixed URL. Pinning these is what makes the
rest of the design safe: an authorization code can only ever be delivered to a genuine Claude
callback, never to an attacker-controlled destination.

You might wonder whether an open registration endpoint is a problem at all. The authorization step
is gated by our Google sign-in, so an outsider who registers a client still cannot get a token. The
attack that remains does not use the outsider's access, but a colleague's: without pinned redirect
URIs, anyone could register a client that delivers authorization codes to their own domain, send a
colleague a link to my very real `/authorize`, and let the auto-approval hand over a code on that
colleague's behalf. PKCE does not help against that, since whoever starts the flow holds the
verifier.

With manually registered clients, an administrator prevents this by vetting redirect URIs and
handing out client IDs and secrets. Dynamic Client Registration automates that administrator away,
so the vetting had to move into code.

Clients register without a secret (`token_endpoint_auth_method: none`): they are *public clients* in
OAuth terms, which is why PKCE is required for them in the server configuration above.

### Where the two OAuth flows meet

Here is my favourite part of the whole design. The `/authorize` endpoint deliberately does **not**
appear in the public `access_control` list, so it falls through to the session firewall that has
protected the dashboard all along. When Claude sends the human to `/authorize`, an anonymous visitor
bounces straight into the Google sign-in that already existed, restricted to the company domain as
always, and comes back to `/authorize` as an authenticated user.

The existing OAuth *client* flow (this app towards Google) has just become the human-identity step
inside the new OAuth *server* flow (Claude towards this app). No new login screen, no separate user
administration for MCP. Being a signed-in employee is the consent.

That is also why the authorization request can be auto-approved instead of showing a consent screen.
This is a single-purpose internal tool, not a platform where third parties request access to your
data. One guard stays in place as defence-in-depth:

```php
#[AsEventListener(event: OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE)]
final readonly class ApproveAuthorization
{
    public function __construct(
        private ClaudeRedirectUris $allowedRedirectUris,
    ) {
    }

    public function __invoke(AuthorizationRequestResolveEvent $event): void
    {
        $redirectUri = $event->getRedirectUri();

        if ($redirectUri !== null && !$this->allowedRedirectUris->allows($redirectUri)) {
            $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_DENIED);

            return;
        }

        $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
    }
}
```

Registration already constrains redirect URIs, so this check should never fire. It exists so that
auto-approval is never unconditional, whatever path created the client.

### The resource server firewall

The last piece is the firewall that guards `/mcp` itself, next to the session firewall and the JWT
firewall the JSON API already had:

```yaml
firewalls:
    mcp:
        pattern: '^/mcp'
        provider: oauth
        stateless: true
        oauth2: true
        entry_point: App\Authentication\OAuth\McpAuthenticationEntryPoint

access_control:
    - { path: '^/token$', role: PUBLIC_ACCESS }
    - { path: '^/register$', role: PUBLIC_ACCESS }
    - { path: '^/\.well-known/oauth', role: PUBLIC_ACCESS }
    # /authorize is deliberately NOT public: it stays behind the session firewall.
    - { path: '^/mcp', role: ROLE_OAUTH2_MCP }
```

The `mcp` scope maps to `ROLE_OAUTH2_MCP`, and requiring that role on `/mcp` doubles as audience
binding: this authorization server is dedicated to this resource and signs with its own key, so
"validated by us and carrying the `mcp` scope" means the token was minted here, for MCP. That is the
guarantee [RFC 8707][_rfc8707]'s resource indicators exist to give. Claude does send a `resource`
parameter on `/authorize` and `/token`, but the league bundle ignores it, which is harmless in this
setup.

## The whole flow, end to end

Adding the server to Claude means pasting one URL. In claude.ai and Claude Desktop that is a
custom connector in the settings, pointing at the endpoint. In a JSON MCP configuration:

```json
{
    "mcpServers": {
        "dashboard": {
            "type": "http",
            "url": "https://.../mcp"
        }
    }
}
```

Claude Code takes the same URL on the command line:

```shell
claude mcp add --transport http dashboard https://.../mcp
```

That is the whole client-side configuration: no client ID, no secret, no token to paste anywhere.
Basically, this happens:

1. Claude calls `POST /mcp` and receives a `401` with
   `WWW-Authenticate: Bearer resource_metadata="https://.../.well-known/oauth-protected-resource"`.
2. It fetches that document, learns which authorization server to talk to (the same host), and
   fetches `/.well-known/oauth-authorization-server`.
3. It registers itself at `/register` and receives a fresh `client_id`.
4. It opens `/authorize` in the browser. The session firewall kicks in: sign in with Google,
   company domain verified server-side.
5. The authorization request is auto-approved after the redirect URI passes the allowlist once more,
   and the authorization code goes to the Claude callback.
6. Claude exchanges the code (plus PKCE verifier) at `/token` for an access token and a refresh
   token.
7. `POST /mcp` again, now with a Bearer token. Tools are listed, and I can finally ask "which
   projects are affected by this CVE?" in plain language.

Step 4 is the only one a human sees, and for a colleague it is just the familiar Google screen.

## What broke along the way

### The transport's DNS-rebinding protection
The MCP SDK protects the HTTP transport against DNS rebinding by checking the `Host` header against
an allowlist that defaulted to localhost, and in the version I started on that list was not configurable.
So the entire OAuth dance would succeed, and then every authenticated request would die with a `403`
before reaching the server. I built a proper, nicely standardised workaround. One day later, Symfony AI
shipped version 0.11.0 with exactly this setting. It was not the first time: the developers at Symfony 
have a habit of releasing a real feature within a week of me finishing a clean workaround for its absence
(this is a good thing). I deleted mine and kept one line of configuration:

```yaml
mcp:
    http:
        path: /mcp
        allowed_hosts: ['%env(BACKEND_DOMAIN)%', 'localhost', '127.0.0.1', '[::1]']
```

### Missing tool annotations
My first version of the tools had names, descriptions and schemas, but no `ToolAnnotations`. Claude, 
quite reasonably, treats a tool without hints as potentially destructive and asks for confirmation on 
every single call. Reading data with a confirmation for every call gets old fast, so: `readOnlyHint: true` 
on every read tool, and suddenly the tools could be approved once and used freely. The hints are not 
cosmetic metadata; they directly shape how the client treats the server.

### The issuer must match exactly
The `issuer` in the authorization-server metadata is string-compared to the base URL the client used 
to fetch the document. Deriving it from the request instead of hardcoding it saved me from a class of 
"works locally, fails behind the proxy" problems.

### Clients probe multiple metadata paths
Some clients fetch `/.well-known/oauth-protected-resource`, others the path-aware `/.well-known/oauth-protected-resource/mcp`.
Serving the same document on both routes is two extra attribute lines and ends the guessing.

## Was it worth it?

The MCP tools themselves took an afternoon; most of them are thin wrappers around queries the
dashboard already ran. The OAuth side was around 280 lines of deliberate code on top of the league
bundle, mostly done with the help of Claude Code.

Now I can ask "which of our projects still run Symfony 6.4?" or "is anything affected by this
morning's advisory?" in a chat window, and the answer comes from my own application, behind the
same Google login as everything else. No new interface, no separate accounts.

[_hwi]: https://github.com/hwi/HWIOAuthBundle
[_mcp]: https://modelcontextprotocol.io/
[_symfony_ai]: https://github.com/symfony/ai
[_mcp_bundle]: https://github.com/symfony/mcp-bundle
[_league]: https://github.com/thephpleague/oauth2-server-bundle
[_rfc9728]: https://datatracker.ietf.org/doc/html/rfc9728
[_rfc8414]: https://datatracker.ietf.org/doc/html/rfc8414
[_rfc7591]: https://datatracker.ietf.org/doc/html/rfc7591
[_rfc8707]: https://datatracker.ietf.org/doc/html/rfc8707
