<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\KeyedLookupEnvy;
use JesseGall\CodeCommandments\Testing\Fixed;

final class CatalogItem
{
    public string $code = '';

    /**
     * @var list<string>
     */
    public array $reserved = [];

    /**
     * Where the registry lookup went. The item held the key all along, so it can answer the
     * question the key was being used to ask.
     *
     * @return list<string>
     */
    #[Fixed(KeyedLookupEnvy::class)]
    public function reservedSkus(): array
    {
        return $this->reserved;
    }
}
