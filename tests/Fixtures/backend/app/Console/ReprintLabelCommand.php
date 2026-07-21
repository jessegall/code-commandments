<?php

namespace Shop\Console;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\BoundaryDuplicatedOperation;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Labels\LabelPrinting;

/**
 * Righteous: the boundary translates its own protocol and DELEGATES. The operation lives in one
 * place, so every face gains a fix at the same moment.
 */
final class ReprintLabelCommand extends Command
{
    protected $signature = 'labels:reprint {sku}';

    #[Righteous(BoundaryDuplicatedOperation::class)]
    public function handle(LabelPrinting $printing): int
    {
        $printing->print((string) $this->argument('sku'));

        return 0;
    }
}
