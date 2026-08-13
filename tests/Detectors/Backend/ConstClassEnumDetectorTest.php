<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ConstClassEnumDetector;
use PHPUnit\Framework\TestCase;

final class ConstClassEnumDetectorTest extends TestCase
{
    public function test_flags_a_scalar_const_class_only(): void
    {
        $code = <<<'PHP'
        <?php
        class Statuses {
            const PENDING = 'pending';
            const PAID = 'paid';
            const SHIPPED = 'shipped';
        }
        class HasBehaviour {
            const PENDING = 'pending';
            const PAID = 'paid';
            public function label(): string { return self::PENDING; }
        }
        class Config {
            const TIMEOUT = 30;
            public int $retries = 3;
        }
        class OneConst {
            const VERSION = '1.0';
        }
        PHP;

        $hits = (new ConstClassEnumDetector)->find(Codebase::fromString($code));
        $names = array_map(static fn ($m): string => $m->enclosingClassName() ?? '?', $hits);

        // Only the pure multi-const scalar class. Not the one with a method, not
        // the one with a property, not the single-const class.
        $this->assertSame(['Statuses'], $names);
    }

    public function test_a_subclass_has_no_enum_to_become(): void
    {
        // #464/#467: a metadata key bag whose base class a package declares. PHP enums extend
        // nothing, so the fix this rule teaches does not exist for a subclass — the finding could
        // only ever be reported, never obeyed. A class that extends nothing is judged as ever.
        $code = <<<'PHP'
        <?php
        class MetaData extends \Spatie\EventSourcing\Enums\MetaData {
            public const string CASHIER_ID = 'cashier-id';
            public const string PACKED_BY = 'packed-by';
            public const string SHOP_ID = 'shop-id';
        }
        class Standalone {
            public const string A = 'a';
            public const string B = 'b';
        }
        PHP;

        $names = array_map(
            static fn ($m): string => $m->enclosingClassName() ?? '?',
            (new ConstClassEnumDetector)->find(Codebase::fromString($code)),
        );

        $this->assertSame(['Standalone'], $names);
    }
}
