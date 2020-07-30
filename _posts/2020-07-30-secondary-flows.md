---
layout: post
title:  "Using Symfony's service iterators for secondary flows"
tagline: "Can we decouple flows without using the EventDispatcher?"
date:   2020-07-30 08:45:00 +0200
categories: php symfony
excerpt: My attempt to decouple secondary flows of a process without using the event system
thanks:
    - iulia
    - bart
    - robin
    - COil
---

Say you're building an application that has users. Those users can register themselves.
You create a controller, perhaps using [Symfony Forms][_forms], to get the data of the
new user and store it in the database. After that, you might want to add secondary
flows. For example to notify both the new user and the admins that the registration was
successful.

## Step 1: Everything in one spot
At first, I usually put everything that needs to happen into the controller. This
way, I can verify that everything works as intended and I always have a working
solution to fall back to if my refactoring breaks something. The result usually ends
up a little like this:

```php
<?php
declare(strict_types=1);

namespace App\Users\Registration;

final class Register
{
    /* ... */

    public function __invoke(Request $request): Response
    {
        // Form or data handling from the request
        $user = new User();
        /* ... */

        // Save the new user
        /* ... */

        // Secondary flow: Notify the user
        $email = (new Email())
            /* ... */
            ->to($user->getEmail())
            ->subject('Welcome to our application!')
            ->text('Some info about our services here!')
            ->html('<p>Some fancier info about our services here!</p>');
        $this->mailer->send($email);

        // Secondary flow: Notify the admins
        $email = (new Email())
            /* ... */
            ->subject('New user!')
            ->text('Some info about the newly registered user here!')
            ->html('<p>Some fancier info about the newly registered user here!</p>');
        $this->mailer->send($email);

        // Create a response
        return new Response(/* ... */);
    }
}
```

## Step 2: Moving secondary steps to separate classes
This approach quickly becomes very cumbersome as the controller will start to
grow out of control (no pun intended) when inevitably you end up adding more
responsibility to it. Moving the secondary flow to separate methods cleans up
the `__invoke()` method, but it still leaves a lot of code and dependencies in
the controller.

What I usually do next is move the secondary stuff, which is everything that's not
needed for the core, most important flow (in this case: filling a user object and
storing it in the database), to separate classes:

```php
<?php
declare(strict_types=1);

namespace App\Users\Registration;

final class NotifyUser
{
    /* ... */

    public function afterUserRegistrationSuccessful(User $user): void
    {
        $email = (new Email())
            /* ... */
            ->to($user->getEmail())
            ->subject('Welcome to our application!')
            ->text('Some info about our services here!')
            ->html('<p>Some fancier info about our services here!</p>');
        $this->mailer->send($email);
    }
}
```

I then inject these classes into the controller through [Dependency Injection][_di]:

```php
<?php
declare(strict_types=1);

namespace App\Users\Registration;

final class Register
{
    /**
     * @var NotifyUser
     */
    private $notifyUser;

    /**
     * @var NotifyAdmins
     */
    private $notifyAdmins;

    public function __construct(/* ... */ NotifyUser $notifyUser, NotifyAdmins $notifyAdmins)
    {
        $this->notifyUser = $notifyUser;
        $this->notifyAdmins = $notifyAdmins;
    }

    public function __invoke(Request $request): Response
    {
        // Form or data handling from the request
        $user = new User();
        /* ... */

        // Save the new user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Secondary flows
        $this->notifyUser->afterUserRegistrationSuccessful($user);
        $this->notifyAdmins->afterUserRegistrationSuccessful($user);

        // Create a response
        return new Response(/* ... */);
    }
}
```

## Step 3: Replace the hardcoded injection of these classes with a service iterator
To not have to change the controller every time I want to add a new bit of secondary
functionality to the flow, I use a service iterator. Service iterators are part of
the [Symfony Dependency Injection][_di]. Using a service iterator will have the Symfony
Dependency Injection look for services that are tagged with a specific tag and
inject them using an iterator for you to loop through these services.

I also add an interface to make sure the method required is always available with
the correct arguments and return type. Both `NotifyUser` and `NotifyAdmins` will
implement this interface.

```php
<?php
declare(strict_types=1);

namespace App\Users\Registration;

interface AfterUserRegistration
{
    public function afterUserRegistrationSuccessful(User $user): void;
}
```

In the controller, I inject the iterator and loop through the services to call
the correct method.

```php
<?php
declare(strict_types=1);

namespace App\Users\Registration;

final class Register
{
    /**
     * @var iterable|AfterUserRegistration[]
     */
    private $secondaryFlows;

    public function __construct(/* ... */ iterable $secondaryFlows)
    {
        $this->secondaryFlows = $secondaryFlows;
    }

    public function __invoke(Request $request): Response
    {
        // Form or data handling from the request
        $user = new User();
        /* ... */

        // Save the new user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        foreach ($this->secondaryFlows as $flow) { 
            if ($flow instanceof AfterUserRegistration) {
                $flow->afterUserRegistrationSuccessful($user);
            }
        }

        // Create a response
        return new Response(/* ... */);
    }
}
```

In the services definitions file, I use the `_instanceof` directive to tag all
classes implementing my interface with a tag, and configure the `$secondaryFlows` argument
of the controller to have a `!tagged_iterator` for the same tag.

```yaml
# config/services.yaml
services:
    # ...
    _instanceof:
        App\Users\Registration\AfterUserRegistration:
            tags: ['app.user.after_registration'] # add this tag to all classes implementing this interface

    App\Users\Registration\Register:
        arguments:
            $secondaryFlows: !tagged_iterator 'app.user.after_registration' # grab all services tagged with this tag
```

## Why I'm not using the Event system
Although I think that the [Symfony Event system][_events] is great for inter-package communication
or to hook into the flow of other bundles, I find that using it within the boundaries
of a single application usually obfuscates the flow and structure of my code. It
adds an extra layer and makes it harder to figure out what exactly happens at a
given time.

The method described above will decouple primary and secondary flows, while still
keeping the services, their methods, and their arguments descriptive. It even allows
data to be returned from each service. All wrapped in an interface that describes exactly
what is to be expected from a secondary flow service.


[_forms]: https://symfony.com/doc/current/forms.html
[_di]: https://symfony.com/doc/current/service_container.html
[_events]: https://symfony.com/doc/current/event_dispatcher.html
