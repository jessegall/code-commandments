<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

final class SecondRecall extends RecallingHook
{
    protected function recall(): string
    {
        return 'Write tests each phase.';
    }
}
