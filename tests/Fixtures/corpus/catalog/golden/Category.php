<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * A catalog category that groups products under a stable key.
 */
final readonly class Category
{
    public function __construct(
        public string $key,
        public string $name,
    ) {}
}
