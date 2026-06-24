<?php

namespace App\Catalog;

class Category
{
    public $key;
    public $name;

    public function __construct(array $data)
    {
        $this->key = $data['key'] ?? null;
        $this->name = $data['name'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return ['key' => $this->key, 'name' => $this->name];
    }
}
