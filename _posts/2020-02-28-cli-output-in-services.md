---
layout: post
title:  "CLI output in services"
tagline: "Can we output information without binding our service to the CLI?"
date:   2020-02-28 16:00:00 +0200
categories: php symfony
excerpt: My path to showing optional CLI output in services and the creation of the Feedback package
thanks:
    - Iulia Stana
    - Bart van Raaij
---

In the projects I work on I often need to build connections with external services.
Most of the time, these projects require periodical data synchronization between
my application and the third party service. In order to do this, I usually
write [Symfony Commands][_command] that can be executed manually or through a
cronjob. Inside these commands, I send information back to the terminal to show
the progress of the synchronization so you can see that something is happening.

To get things up and running, I put everything in a single [Symfony's Command class][_command_class]
that contains _all_ the code needed to perform the task inside the `execute()` method.
Because this is not a very [SOLID][_solid] approach, I then refactor code from
this method into separate services and classes. I usually end up with a `Synchronizer`
service that uses a `Repository` class to store data locally and a `Client` class
to pull data from a remote source (or vice versa, depending on the requirements).

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

And here we get to the core problem. How can we make sure that this `Synchronizer`
service can still let the CLI know what it's doing (and how far it is in the process),
without binding CLI output so much to the service, that it's unusable from any other
access point (like controllers, event listeners etc)? 

## First try, using callbacks
At first, I tried adding closures to the `synchronize()` method of the `Synchronizer`.
This, however, ended up quite cumbersome as the amount of parameters increased a
lot, with a lot of code in the `Command`. Also, when calling this method, it
is quite unclear what kind of parameters are expected in each closure.

```php
final class Synchronizer
{
    /* ... */

    public function synchronize(\Closure $info, \Closure $startProcess, \Closure $advanceProcess, \Closure $stopProcess): void
    {
        $info('Starting synchronization');

        $data = $this->client->loadProducts();

        $info(\sprintf('Loaded %d products from remote source.', \count($data)));
        $products = [];

        $startProcess(\count($data));
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

            $advanceProcess();
        }
        $stopProcess();

        $this->repository->store(...$products);

        $info('Done synchronizing');
    }
}
```

## The Feedback Class
In order to avoid the [long parameter list code smell][_long_parameters_list],
I wrapped the closure's functionality in a single class that can be used in
the `Synchronizer`. The first version of the `Feedback` class I made took a
`SymfonyStyle` object in the constructor and used that to format the CLI output
nicely.

Because the `$feedback` variable could be null (when called from a controller or
event listener), it means the `synchronize` method has to do a lot of null checks:

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
            $feedback->info(\sprintf('Loaded %d products from remote source.', \count($data)));
        }

        /* ... */
    }
}
```

## The fallback: NoFeedback
In order to not have to check for the existence of `$feedback` ever time you
want to use it, I created the `NoFeedback` class as implementation of [the null object pattern][_null_object_pattern].
This class has all the same methods, but without any actual executing code.

To keep the namespace clean, I renamed the `Feedback` class to `SymfonyStyleFeedback`
and reused `Feedback` to create an interface out of the previous class.

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

        $feedback->info(\sprintf('Loaded %d products from remote source.', \count($data)));

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
After receiving positive feedback from the community regarding the usefulness
of this solution, I requested permission from my employer to open source it
as a package and [Linku][_linku] has graciously agreed.

To make sure that `Feedback` is also usable outside of Symfony projects, I
split the code over two packages:
1. The [Linku/Feedback package][_feedback_package] includes three readily usable
implementations of `Feedback`:
    - `ClosureFeedback` to use a custom closure for each of the methods
    - `LoggerFeedback` to send information to any [PSR-3 Logger][_psr3]
    - `ChainedFeedback` to allow multiple `Feedback` implementations to be used at the same time
1. The [Linku/Feedback-SymfonyStyle package][_symfonystyle_package] includes a single
implementation that uses `SymfonyStyle` to style output to the CLI.

Give `Feedback` a try. If you have any requests, questions or improvements, please
open an issue or pull request in [Github][_github] or reach out to me on [Twitter][_twitter].


[_command]: https://symfony.com/doc/current/console.html
[_command_class]: https://github.com/symfony/symfony/blob/5.0/src/Symfony/Component/Console/Command/Command.php
[_solid]: https://en.wikipedia.org/wiki/SOLID
[_long_parameters_list]: https://blog.codinghorror.com/code-smells/
[_null_object_pattern]: https://en.wikipedia.org/wiki/Null_object_pattern
[_linku]: https://linku.nl/
[_feedback_package]: https://packagist.org/packages/linku/feedback
[_psr3]: https://www.php-fig.org/psr/psr-3/
[_symfonystyle_package]: https://packagist.org/packages/linku/feedback-symfonystyle
[_github]: https://github.com/linkunijmegen/
[_twitter]: https://twitter.com/TimoBakx/
