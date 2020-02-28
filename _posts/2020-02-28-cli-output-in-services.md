---
layout: post
title:  "CLI output in services"
tagline: "Can we output information without binding our service to the CLI?"
date:   2020-02-28 16:00:00 +0200
categories: php symfony
---

At my work, I tend to build a lot of connections with external services. This
often includes situations where data needs to be synchronized between my application
and a third party service periodically. In order to do this, I usually write
[Symfony Commands][_command] that can be executed manually or through a cronjob.

At first, this is one class that extends [Symfony's Command class][_command_class]
and contains _all_ the code needed to perform the task inside the `execute()` method.
Because this is not a very [SOLID][_solid] approach, I then refactor code from
this method into separate services and classes.

I usually end up with a `Synchronizer` service that uses a `Repository` class
to store data locally and a `Client` class to pull data from a remote source
(or vice versa, depending on the requirements).

```php
final class Synchronizer
{
    /* ... */

    public function synchronize(): void
    {
        $data = $this->client->loadProducts();
        $products = [];

        foreach ($data as $row) {
            try {
                $product = $this->repository->getByExternalId($row['id']);
            } catch (ProductNotFound $exception) {
                $product = new Product();
                $product->setExternalId($row['id']);
            }

            $product->setTitle($row['title']);
            $product->setPrice((float)$row['price']);

            $products[] = $product;
        }

        $this->repository->store(...$products);
    }
}
```

And here we come to the problem at hand. How can we make sure that this `Synchronizer`
service can still let the CLI know what it's doing (and how far it is in the process),
without binding CLI output so much to the service, that it's unusable from any other
access point (like controllers, event listeners etc)? 

## First try, using callbacks
At first, I tried adding closures to the `synchronize()` method of the `Synchronizer`.
This, however, ended up quite cumbersome as the amount of parameters increased a
lot, with a lot of code in the `Command`. Also, from the point of view of calling
this method, it is entirely unclear what kind of parameters are expected in each closure.

```php
final class Synchronizer
{
    /* ... */

    public function synchronize(\Closure $info, \Closure $startProgress, \Closure $advanceProgress, \Closure $stopProgress): void
    {
        $info('Starting synchronization');

        $data = $this->client->loadProducts();

        $info(sprintf('Loaded %d products from remote source.', \count($data)));
        $products = [];

        $startProgress(\count($data));
        foreach ($data as $row) {
            $externalID = $row['id'];

            try {
                $product = $this->repository->getByExternalId($externalID);
            } catch (ProductNotFound $exception) {
                $product = new Product();
                $product->setExternalId($externalID);
            }

            $product->setTitle($row['title']);
            $product->setPrice((float)$row['price']);

            $products[] = $product;

            $advanceProgress();
        }
        $stopProgress();

        $this->repository->store(...$products);

        $info('Done synchronizing');
    }
}
```

## The Feedback Class
A better way to do this is to wrap the functionality that's now handled inside
the closures in a single class that can be used in the `Synchronizer`. The
first version of the `Feedback` class I made took a `SymfonyStyle` object
and used that to format the CLI output nicely.

Unfortunately, the code still had a lot of logic in case no `Feedback` instance 
was passed to the method.

```php
use App\Feedback\Feedback;

final class Synchronizer
{
    /* ... */

    public function synchronize(?Feedback $feedback = null): void
    {
        if ($feedback) {
            $feedback->info('Starting synchronization');
        }

        $data = $this->client->loadProducts();

        if ($feedback) {
            $feedback->info(sprintf('Loaded %d products from remote source.', \count($data)));
        }
        $products = [];

        if ($feedback) {
            $feedback->startProgress(\count($data));
        }
        foreach ($data as $row) {
            $externalID = $row['id'];

            try {
                $product = $this->repository->getByExternalId($externalID);
            } catch (ProductNotFound $exception) {
                $product = new Product();
                $product->setExternalId($externalID);
            }

            $product->setTitle($row['title']);
            $product->setPrice((float)$row['price']);

            if ($feedback) {
                $feedback->advanceProgress();
            }
        }
        if ($feedback) {
            $feedback->stopProgress();
        }

        $this->repository->store(...$products);

        if ($feedback) {
            $feedback->info('Done synchronizing');
        }
    }
}
```

## The fallback: NoFeedback
In order to not have to check for the existence of `$feedback` ever time you
want to use it, I created a `NoFeedback` class. This class has all the same
methods, but without any actual executing code. I renamed the `Feedback` class
to `SymfonyStyleFeedback` so I can use `Feedback` as an interface.

```php
use App\Feedback\Feedback;
use App\Feedback\NoFeedback;

final class Synchronizer
{
    /* ... */

    public function synchronize(?Feedback $feedback = null): void
    {
        if (!$feedback) {
            $feedback = new NoFeedback();
        }

        $data = $this->client->loadProducts();

        $feedback->info(sprintf('Loaded %d products from remote source.', \count($data)));

        /* ... */
    }
}
```

## Moving Feedback from method to class
Eventually, I moved the `Feedback` instance used to the service itself instead
of inside the method that uses it. This led to less boilerplate code per method
in the service and a reusable instance in case the service had more than one
method.

```php
use App\Feedback\Feedback;
use App\Feedback\NoFeedback;

final class Synchronizer
{
    /**
     * @var Repository
     */
    private $repository;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Feedback
     */
    private $feedback;

    public function __construct(Repository $repository, Client $client)
    {
        $this->repository = $repository;
        $this->client = $client;
        $this->feedback = new NoFeedback();
    }

    public function setFeedback(Feedback $feedback): void
    {
        $this->feedback = $feedback;
    }

    /* ... */
}
```

## Using it yourself
Thanks to my bosses, I was allowed to share this way of handling output with
the world, as [Linku][_linku] open sourced the Feedback package. To make sure
that Feedback is also usable outside of Symfony projects, I split the code over
two packages: [Linku/Feedback][_feedback_package] (with the interface and a
few core implementations) and [Linku/Feedback-SymfonyStyle][_symfonystyle_package]
(with a Symfony specific implementation).

The core implementations include a `ClosureFeedback` to use a custom closure for
each of the methods, a `LoggerFeedback` that sends information to any [PSR-3 Logger][_psr3],
and a `ChainedFeedback` that allows multiple `Feedback` implementations to be
used at the same time.


[_command]: https://symfony.com/doc/current/console.html
[_command_class]: https://github.com/symfony/symfony/blob/5.0/src/Symfony/Component/Console/Command/Command.php
[_solid]: https://en.wikipedia.org/wiki/SOLID
[_linku]: https://linku.nl/
[_feedback_package]: https://packagist.org/packages/linku/feedback
[_symfonystyle_package]: https://packagist.org/packages/linku/feedback-symfonystyle
[_psr3]: https://www.php-fig.org/psr/psr-3/
