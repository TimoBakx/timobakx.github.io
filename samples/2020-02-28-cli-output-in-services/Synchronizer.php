<?php
declare(strict_types=1);

namespace App\Product;

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
        }

        $this->repository->store(...$products);
    }

    public function synchronizeClosures(\Closure $info, \Closure $startProcess, \Closure $advanceProcess, \Closure $stopProcess): void
    {
        $info('Starting synchronization');

        $data = $this->client->loadProducts();

        $info(sprintf('Loaded %d products from remote source.', \count($data)));
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

    public function synchronizeFeedbackClass(?Feedback $feedback = null): void
    {
        if ($feedback) {
            $feedback->info('Starting synchronization');
        }

        $data = $this->client->loadProducts();

        if ($feedback) {
            $feedback->info(\sprintf('Loaded %d products from remote source.', \count($data)));
        }
        $products = [];

        if ($feedback) {
            $feedback->startProcess(\count($data));
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

            $products[] = $product;

            if ($feedback) {
                $feedback->advanceProcess();
            }
        }
        if ($feedback) {
            $feedback->stopProcess();
        }

        $this->repository->store(...$products);

        if ($feedback) {
            $feedback->info('Done synchronizing');
        }
    }

    public function synchronizeFeedback(?Feedback $feedback = null): void
    {
        if ($feedback === null) {
            $feedback = new NoFeedback();
        }

        $feedback->info('Starting synchronization');

        $data = $this->client->loadProducts();

        $feedback->info(sprintf('Loaded %d products from remote source.', \count($data)));
        $products = [];

        $feedback->startProcess(\count($data));
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

            $feedback->advanceProcess();
        }
        $feedback->stopProcess();

        $this->repository->store(...$products);

        $feedback->info('Done synchronizing');
    }

    public function setFeedback(Feedback $feedback): void
    {
        $this->feedback = $feedback;
    }

    public function synchronizeWithoutOwnFeedback(): void
    {
        $this->feedback->info('Starting synchronization');

        $data = $this->client->loadProducts();

        $this->feedback->info(sprintf('Loaded %d products from remote source.', \count($data)));
        $products = [];

        $this->feedback->startProcess(\count($data));
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

            $this->feedback->advanceProcess();
        }
        $this->feedback->stopProcess();

        $this->repository->store(...$products);

        $this->feedback->info('Done synchronizing');
    }
}
