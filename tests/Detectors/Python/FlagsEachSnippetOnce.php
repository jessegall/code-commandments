<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Each of the class's `thisSin` cases is one finding — for a {@see ProvesAPythonRule} whose sin shows
 * once per snippet.
 */
trait FlagsEachSnippetOnce
{
    #[DataProvider('thisSin')]
    public function test_flags_this_sin(string $source): void
    {
        $this->assertCount(1, $this->findIn($source));
    }
}
