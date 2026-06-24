<?php

declare(strict_types=1);

namespace App\Catalog;

use Illuminate\Support\Collection;

/**
 * Reads stored products, always returning a collection (empty for none).
 */
interface ProductRepository
{
    /**
     * @return Collection<int, Product>
     */
    public function all(): Collection;
}
