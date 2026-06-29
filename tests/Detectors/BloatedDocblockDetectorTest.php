<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\BloatedDocblockDetector;
use PHPUnit\Framework\TestCase;

final class BloatedDocblockDetectorTest extends TestCase
{
    public function test_flags_a_multi_paragraph_class_docblock_only(): void
    {
        $code = <<<'PHP'
        <?php
        /**
         * Does a lot of things across the whole system, reads files, maps rows,
         * talks to three services and writes the result back.
         *
         * It also handles retries and logging, and used to live elsewhere.
         */
        class Bloated {}

        /**
         * A focused, single-line summary of what this is.
         */
        class Lean {}

        class Bare {}
        PHP;

        $hits = (new BloatedDocblockDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingClassName() ?? '?', $hits);

        $this->assertSame(['Bloated'], $names);
    }
}
