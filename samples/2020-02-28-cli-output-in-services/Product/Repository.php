<?php
declare(strict_types=1);

namespace App\Product;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class Repository
{
    /**
     * @var EntityManagerInterface
     */
    private $manager;

    /**
     * @var EntityRepository
     */
    private $repository;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->manager = $managerRegistry->getManagerForClass(Product::class);
        $this->repository = $managerRegistry->getRepository(Product::class);
    }

    public function getByExternalId($id): Product
    {
        $product = $this->repository->findByExternalId($id);

        if (!$product instanceof Product) {
            throw ProductNotFound::byExternalId($id);
        }

        return $product;
    }

    public function store(Product ...$products): void
    {
        foreach ($products as $product) {
            $this->manager->persist($product);
        }

        $this->manager->flush();
    }
}
