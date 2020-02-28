<?php
declare(strict_types=1);

namespace App\Product;

final class Client
{
    public function loadProducts(): array
    {
        return [
            [
                'id' => 'product_1',
                'title' => 'Product 1',
                'price' => '10.95',
            ],
            [
                'id' => 'product_2',
                'title' => 'Product 2',
                'price' => '9.45',
            ],
        ];
    }
}
