<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\StringMatchMirrorsEnumDetector;
use PHPUnit\Framework\TestCase;

final class StringMatchMirrorsEnumDetectorTest extends TestCase
{
    public function test_flags_a_match_over_strings_that_mirror_an_enum_only(): void
    {
        $code = <<<'PHP'
        <?php
        enum Status: string {
            case Pending = 'pending';
            case Paid = 'paid';
            case Shipped = 'shipped';
        }
        class S {
            public function mirrors(string $status): string {
                return match ($status) {
                    'pending' => 'grey',
                    'paid' => 'green',
                    default => 'black',
                };
            }
            public function unrelated(string $dir): string {
                return match ($dir) {
                    'asc' => 'up',
                    'desc' => 'down',
                };
            }
            public function onValue(Status $s): string {
                return match ($s->value) {
                    'pending' => 'grey',
                    'paid' => 'green',
                };
            }
        }
        PHP;

        $hits = (new StringMatchMirrorsEnumDetector)->find(Codebase::fromString($code));

        // mirrors: 'pending'/'paid' are Status cases. unrelated: no enum. onValue:
        // the ->value form (EnumValueMatch's job), excluded here.
        $this->assertSame(['S::mirrors'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
