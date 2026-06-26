<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector;
use PHPUnit\Framework\TestCase;

final class ArrayBagDetectorTest extends TestCase
{
    public function test_flags_string_indexing_of_an_array_parameter_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S
        {
            public function render(array $b): string
            {
                return (string) $b['total'];
            }

            public function lookup(array $m, string $k): mixed
            {
                return $m[$k] ?? null;
            }

            public function first(array $cols): string
            {
                return (string) ($cols[0] ?? '');
            }

            public function local(): string
            {
                $x = ['total' => 1];

                return (string) $x['total'];
            }
        }
        PHP;

        $hits = (new ArrayBagDetector)->find(Codebase::fromString($code));

        // only render() string-indexes an ARRAY PARAMETER by a literal key
        $this->assertSame(['S::render'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
