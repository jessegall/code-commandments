<?php

namespace Shop\Imports\Csv;

use Shop\Imports\ImportFormat;

final class SemicolonSeparated implements ImportFormat
{
    public function extension(): string
    {
        return 'csv';
    }
}
