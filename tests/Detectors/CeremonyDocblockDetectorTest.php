<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\CeremonyDocblockDetector;
use PHPUnit\Framework\TestCase;

final class CeremonyDocblockDetectorTest extends TestCase
{
    public function test_flags_a_docblock_that_only_restates_the_signature(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            /**
             * @param int $count
             * @param string $name
             */
            public function restates(int $count, string $name): void {}

            /**
             * @param int<1, 9> $count the priority band
             */
            public function refines(int $count): void {}

            /**
             * Builds the widget from its parts.
             *
             * @param int $count
             */
            public function described(int $count): void {}
        }
        PHP;

        $hits = (new CeremonyDocblockDetector)->find(Codebase::fromString($code));

        $this->assertSame(['S::restates'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
