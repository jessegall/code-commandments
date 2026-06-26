<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\EnumCaseOrChainDetector;
use PHPUnit\Framework\TestCase;

final class EnumCaseOrChainDetectorTest extends TestCase
{
    public function test_flags_an_or_chain_over_two_cases_of_the_same_enum(): void
    {
        $code = <<<'PHP'
        <?php
        enum Status: string {
            case Pending = 'pending';
            case Paid = 'paid';
            case Shipped = 'shipped';
        }
        class S {
            public function isOpen(Status $s): bool {
                return $s === Status::Pending || $s === Status::Paid;
            }
            public function single(Status $s): bool {
                return $s === Status::Shipped;
            }
        }
        PHP;

        $hits = (new EnumCaseOrChainDetector)->find(Codebase::fromString($code));

        $this->assertSame(['S::isOpen'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
