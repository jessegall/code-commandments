<?php

namespace Shop\Refunds;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedFolderPath;
use JesseGall\CodeCommandments\Testing\Fixed;

final class RefundFolder
{
    #[Fixed(RepeatedFolderPath::class)]
    public function file(string $name): string
    {
        return storage_path('refunds/receipts/' . $name);
    }
}
