<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

final class FirstRecall extends RecallingHook
{
    protected function recall(): string
    {
        return 'No frontend logic.';
    }
}
