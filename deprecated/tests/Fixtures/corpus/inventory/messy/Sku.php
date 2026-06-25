<?php

namespace App\Inventory;

class Sku
{
    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function equals($other)
    {
        return $this->code == $other->code;
    }
}
