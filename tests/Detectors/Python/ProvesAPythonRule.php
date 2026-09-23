<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A Python rule proven on snippets: each of the class's `notThisSin` cases is left alone.
 */
trait ProvesAPythonRule
{
    abstract private function rule(): Detector;

    #[DataProvider('notThisSin')]
    public function test_leaves_what_is_not_this_sin(string $source): void
    {
        $this->assertSame([], $this->findIn($source));
    }

    /**
     * @return list<Located>
     */
    private function findIn(string $source): array
    {
        return $this->rule()->find(Codebase::fromString($source));
    }
}
