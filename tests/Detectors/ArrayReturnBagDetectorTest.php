<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayReturnBagDetector;
use PHPUnit\Framework\TestCase;

final class ArrayReturnBagDetectorTest extends TestCase
{
    public function test_flags_a_returned_multi_field_string_keyed_array_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S
        {
            public function bag(): array
            {
                return ['subtotal' => 1, 'tax' => 2, 'total' => 3];
            }

            public function wrapped(): array
            {
                return ['ok' => true];
            }

            public function items(): array
            {
                return [1, 2, 3];
            }

            public function passthrough(array $x): array
            {
                return $x;
            }
        }
        PHP;

        $hits = (new ArrayReturnBagDetector)->find(Codebase::fromString($code));

        $this->assertSame(['S::bag'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
