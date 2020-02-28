<?php
declare(strict_types=1);

namespace App\Product;

use RuntimeException;

final class ProductNotFound extends RuntimeException
{
    public static function byExternalId(string $externalId): self
    {
        return new self(sprintf('No product found with external ID "%s"', $externalId));
    }
}
