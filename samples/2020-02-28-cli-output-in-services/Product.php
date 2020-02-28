<?php
declare(strict_types=1);

namespace App\Product;

class Product
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $externalId;

    /**
     * @var string
     */
    private $title;

    /**
     * @var float
     */
    private $price;

    public function getId(): int
    {
        return $this->id;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }
}
