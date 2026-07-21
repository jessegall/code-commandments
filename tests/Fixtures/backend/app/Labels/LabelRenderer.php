<?php

namespace Shop\Labels;

/** Turns a SKU into printable ZPL. */
final class LabelRenderer
{
    public function render(string $sku): string
    {
        return '^XA' . $sku . '^XZ';
    }
}
