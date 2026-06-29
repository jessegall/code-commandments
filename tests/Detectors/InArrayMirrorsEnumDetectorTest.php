<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\InArrayMirrorsEnumDetector;
use PHPUnit\Framework\TestCase;

final class InArrayMirrorsEnumDetectorTest extends TestCase
{
    public function test_flags_in_array_over_enum_values_only(): void
    {
        $code = <<<'PHP'
        <?php
        enum Status: string {
            case Pending = 'pending';
            case Paid = 'paid';
            case Shipped = 'shipped';
        }
        class S {
            public function mirrors(string $x): bool {
                return in_array($x, ['pending', 'paid'], true);
            }
            public function unrelated(string $x): bool {
                return in_array($x, ['asc', 'desc'], true);
            }
        }
        PHP;

        $hits = (new InArrayMirrorsEnumDetector)->find(Codebase::fromString($code));

        $this->assertSame(['S::mirrors'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
