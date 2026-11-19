<?php

namespace Shop\Refunds;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedFolderPath;
use JesseGall\CodeCommandments\Testing\Fixed;

final class RefundReceipts
{
    public function __construct(private RefundFolder $folder) {}

    #[Fixed(RepeatedFolderPath::class)]
    public function store(string $refund, string $pdf): void
    {
        file_put_contents($this->folder->file($refund . '.pdf'), $pdf);
    }

    #[Fixed(RepeatedFolderPath::class)]
    public function exists(string $refund): bool
    {
        return is_file($this->folder->file($refund . '.pdf'));
    }

    #[Fixed(RepeatedFolderPath::class)]
    public function remove(string $refund): bool
    {
        return unlink($this->folder->file($refund . '.pdf'));
    }
}
