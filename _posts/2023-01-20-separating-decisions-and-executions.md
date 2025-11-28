---
layout: post
title:  "Separating decisions and executions"
tagline: "Can we separate decision making and the actual execution that follows that decision?"
date:   2023-01-20 21:00:00 +0200
categories: php symfony
excerpt: "How I separate decisions and executions using Symfony's service locators"
thanks:
---

Say you're building an application that is sending data to a third party application
or SaaS solution using their API. There would be two flows to store data, one to add
new objects and one to update an existing one. We would store the ID of the third
party object as `externalId` in our own database. Checking that field for a null value,
we can determine if the object should be added or updated.

In this example, I'll be handling tasks and send them to a SaaS todo application.
I assume that there is a `Client` service that has methods to send data directly
to the third party application. I'll use [Todoist][_todoist] in the example.

Because I usually use API Platform in my applications, the entry point in this example
will be an event listener that hooks into the `POST_WRITE` [event of API Platform][_apip_event]
that triggers after an API Platform resource is written to the database (during a 
`POST`, `PUT` or `DELETE` operation).

You might note that I use an interface instead of binding the event listener manually.
I created a [bundle][_apip_event_bundle] to do this automatically to save myself
some wiring in each of my projects.

## Step 1: Everything in 1 spot
I usually create just the event listener at first, and have it do all of the different
flows by itself.

```php
<?php

declare(strict_types=1);

namespace App\Task;

use App\Todoist\Client;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use TimoBakx\ApiEventsBundle\ApiPlatformListeners\AfterWrite;

final class SendToTodoist implements AfterWrite
{
    public function __construct(
        private readonly Client $client,
        private readonly Store $store,
    )
    {
    }

    public function __invoke(ViewEvent $event): void
    {
        $task = $event->getControllerResult();

        if (!$task instanceof Task) {
            return;
        }

        if ($task->externalId === null) {
            $result = $this->client->post(
                '/tasks/',
                [
                    'content' => $task->title,
                    'due' => $task->due->format(\DateTimeInterface::ATOM),
                    'priority' => $task->priority,
                ]
            );

            $task->externalId = $result['id'];

            $this->store->save($task);

            return;
        }

        $this->client->put(
            sprintf('/tasks/%s', $task->externalId),
            [
                'content' => $task->title,
                'due' => $task->due->format(\DateTimeInterface::ATOM),
                'priority' => $task->priority,
            ]
        );
    }
}
```

As you can imagine, this event listener might grow quite large, especially if there
are more than two different flows (for example deleting, changing status or delegating).

## Step 2: Move the two flows to services
We might want to be a bit more Single Responsibility and move the "adding a new task
in Todoist" and "updating an existing task in todoist" to two separate services.

Because we now have multiple classes that are involved into sending tasks to Todoist,
we'll created a new `App\Task\Todoist` namespace. I usually prefer to put everything
involving _direct_ communication with a third party service in a top level namespace
inside my `App\` (in this case `App\Todoist`), while putting the connection between
my domain and third party services in a subnamespace of the domain (in this case `App\Task\Todoist`).

The two services would look like this:
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Store;
use App\Task\Task;
use App\Todoist\Client;

final class Adder
{
    public function __construct(
        private readonly Client $client,
        private readonly Store $store,
    )
    {
    }

    public function add(Task $task): void
    {
        $result = $this->client->post(
            '/tasks/',
            [
                'content' => $task->title,
                'due' => $task->due->format(\DateTimeInterface::ATOM),
                'priority' => $task->priority,
            ]
        );

        $task->externalId = $result['id'];

        $this->store->save($task);
    }
}

```

```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;
use App\Todoist\Client;

final class Updater
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function update(Task $task): void
    {
        $this->client->put(
            sprintf('/tasks/%s', $task->externalId),
            [
                'content' => $task->title,
                'due' => $task->due->format(\DateTimeInterface::ATOM),
                'priority' => $task->priority,
            ]
        );
    }
}

```

And the event listener can then be refactored to use these services:
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use TimoBakx\ApiEventsBundle\ApiPlatformListeners\AfterWrite;

final class SendToTodoist implements AfterWrite
{
    public function __construct(
        private readonly Adder $adder,
        private readonly Updater $updater,
    )
    {
    }

    public function __invoke(ViewEvent $event): void
    {
        $task = $event->getControllerResult();

        if (!$task instanceof Task) {
            return;
        }

        if ($task->externalId === null) {
            $this->adder->add($task);

            return;
        }

        $this->updater->update($task);
    }
}
```

## Step 4: Move the decision to a service
The next step would be to move the selection of which service to use when into a
separate service of its own. It will return either executing service.

We'll have to generalize both services with an interface to make sure the event listener
can call a single method on either service. I'll rename the services to "handlers" that
use the `Handler` interface.

The interface itself looks like this:
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;

interface Handler
{
    public function handle(Task $task): void;
}
```

The two services are refactored so they use that interface:
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Store;
use App\Task\Task;
use App\Todoist\Client;

final class CreateHandler implements Handler
{
    public function __construct(
        private readonly Client $client,
        private readonly Store $store,
    )
    {
    }

    public function handle(Task $task): void
    {
        $result = $this->client->post(
            '/tasks/',
            [
                'content' => $task->title,
                'due' => $task->due->format(\DateTimeInterface::ATOM),
                'priority' => $task->priority,
            ]
        );

        $task->externalId = $result['id'];

        $this->store->save($task);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;
use App\Todoist\Client;

final class UpdateHandler implements Handler
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function handle(Task $task): void
    {
        $this->client->put(
            sprintf('/tasks/%s', $task->externalId),
            [
                'content' => $task->title,
                'due' => $task->due->format(\DateTimeInterface::ATOM),
                'priority' => $task->priority,
            ]
        );
    }
}
```

We create a new service that returns either one of those services, based on the status
of a given `Task`:
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;

final class Handlers
{
    public function __construct(
        private readonly CreateHandler $createHandler,
        private readonly UpdateHandler $updateHandler,
    ) {
    }

    public function get(Task $task): Handler
    {
        if ($task->externalId === null) {
            return $this->createHandler;
        }

        return $this->updateHandler;
    }
}
```

And refactor the event listener to use this new service:
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use TimoBakx\ApiEventsBundle\ApiPlatformListeners\AfterWrite;

final class SendToTodoist implements AfterWrite
{
    public function __construct(
        private readonly Handlers $handlers,
    ) {
    }

    public function __invoke(ViewEvent $event): void
    {
        $task = $event->getControllerResult();

        if (!$task instanceof Task) {
            return;
        }

        $this->handlers
            ->get($task)
            ->handle($task);
    }
}
```

## Step 5: Tagging & Locating
To make the loading of the handlers a little more lazy (now the services are both
instantiated, even if we're only using one), we're going to use a Service Locator.
Service locators are part of the [Symfony Dependency Injection][_di]. Using a 
service locator will have the Symfony Dependency Injection look for services that 
are tagged with a specific tag and inject them using a `ContainerInterface` for you
to fetch specific services from the container.

We'll have to rewrite the `Handlers` service a little to use a `ContainerInterface`
instead of the two injected handlers:

```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;
use Psr\Container\ContainerInterface;

final class Handlers
{
    public function __construct(
        private readonly ContainerInterface $locator,
    ) {
    }

    public function get(Task $task): Handler
    {
        if ($task->externalId === null) {
            return $this->locator->get(CreateHandler::class);
        }

        return $this->locator->get(UpdateHandler::class);
    }
}
```

Next, we'll have to add a bit of configuration to the `services.yaml` file, to make
sure the `ContainerInterface` is filled with the services we need:
```yaml
# config/services.yaml
services:
    # ...
    _instanceof:
        App\Task\Todoist\Handler:
            tags: ['app.task.todoist_handler'] # add this tag to all classes implementing this interface

    App\Task\Todoist\Handlers:
        arguments:
            $locator: !tagged_locator 'app.task.todoist_handler' # grab all services tagged with this tag
```

## Further finetuning of the Service Locator
In some cases, we might not want to use the entire FQCN of a service to pull it out
of the service locator. I've had a few cases where the decision of which service
to use was stored in the database, where moving (refactoring) a service to a different
namespace might break the code if the FQCN is stored in the database. In that case,
I often add a static method that returns a hardcoded shortname for the service, include
that method in the interface, and tell Symfony to use that method as key to search
in the container.

In the `Handler` interface, we add a static `getName` method:
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;

interface Handler
{
    public static function getName(): string;

    public function handle(Task $task): void;
}
```

Next, we implement this method in the two handlers:
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Store;
use App\Task\Task;
use App\Todoist\Client;

final class CreateHandler implements Handler
{
    public function __construct(
        private readonly Client $client,
        private readonly Store $store,
    )
    {
    }

    public static function getName(): string
    {
        return 'create';
    }

    public function handle(Task $task): void
    {
        // ...
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;
use App\Todoist\Client;

final class UpdateHandler implements Handler
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public static function getName(): string
    {
        return 'update';
    }

    public function handle(Task $task): void
    {
        // ...
    }
}
```

We tell Symfony to use this new method to index the handlers:
```yaml
# config/services.yaml
services:
    # ...
    _instanceof:
        App\Task\Todoist\Handler:
            tags: ['app.task.todoist_handler']

    App\Task\Todoist\Handlers:
        arguments:
            $locator: !tagged_locator { tag: 'app.task.todoist_handler', default_index_method: 'getName' }
```

Finally, we should use the names instead of the FQCN in the `Handlers` service (at least for our example):
```php
<?php

declare(strict_types=1);

namespace App\Task\Todoist;

use App\Task\Task;
use Psr\Container\ContainerInterface;

final class Handlers
{
    public function __construct(
        private readonly ContainerInterface $locator,
    ) {
    }

    public function get(Task $task): Handler
    {
        if ($task->externalId === null) {
            return $this->locator->get('create');
        }

        return $this->locator->get('update');
    }
}
```

[_todoist]: https://todoist.com/
[_apip_event]: https://api-platform.com/docs/core/events/#custom-event-listeners
[_apip_event_bundle]: https://packagist.org/packages/timobakx/api-events-bundle
[_di]: https://symfony.com/doc/current/service_container.html
