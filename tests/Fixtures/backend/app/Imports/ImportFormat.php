<?php

namespace Shop\Imports;

/**
 * A file layout the product import can read.
 */
interface ImportFormat
{
    public function extension(): string;
}
