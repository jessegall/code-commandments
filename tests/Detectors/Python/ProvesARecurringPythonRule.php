<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A rule that finds one shape written in several places: each `recurring` source yields a finding on
 * every line it names, and each `notThisSin` source yields none.
 */
trait ProvesARecurringPythonRule
{
    use ProvesAPythonRule;

    /**
     * @param  list<int>  $lines
     */
    #[DataProvider('recurring')]
    public function test_flags_every_copy(string $source, array $lines): void
    {
        $this->assertSame($lines, array_map(static fn (ExprMatch $finding): int => $finding->line(), $this->findIn($source)));
    }
}
