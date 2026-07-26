<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MemberOutOfOrderDetector;
use PHPUnit\Framework\TestCase;

/**
 * The head of a class runs in one fixed sequence: trait uses, enum cases, constants, static
 * properties, instance properties public → protected → private, hooked (derived) properties last.
 */
final class MemberOutOfOrderDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = (new MemberOutOfOrderDetector)->find(Codebase::fromString($code));

        return array_map(static fn ($m): string => $m->scope(), $hits);
    }

    public function test_flags_a_constant_declared_under_a_property(): void
    {
        $code = <<<'PHP'
        <?php
        class Retry
        {
            private int $attempts = 0;

            private const int MAX = 3;

            public function attempt(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Retry'], $this->scopes($code));
    }

    public function test_flags_a_public_field_declared_under_a_private_one(): void
    {
        $code = <<<'PHP'
        <?php
        class Cart
        {
            private array $lines = [];

            public string $currency = 'EUR';

            public function add(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Cart'], $this->scopes($code));
    }

    public function test_flags_a_static_property_under_an_instance_one(): void
    {
        $code = <<<'PHP'
        <?php
        class Counter
        {
            public int $current = 0;

            public static int $total = 0;

            public function tick(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Counter'], $this->scopes($code));
    }

    public function test_flags_a_hook_declared_above_the_fields_it_reads(): void
    {
        $code = <<<'PHP'
        <?php
        class Invoice
        {
            public string $reference {
                get => strtoupper($this->number);
            }

            public string $number = 'inv-1';

            public function issue(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Invoice'], $this->scopes($code));
    }

    public function test_flags_a_trait_use_under_a_constant(): void
    {
        $code = <<<'PHP'
        <?php
        class Order
        {
            private const int PRECISION = 2;

            use HasTimestamps;

            public function total(): void
            {
            }
        }
        PHP;

        $this->assertSame(['Order'], $this->scopes($code));
    }

    public function test_leaves_the_canonical_head_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class Invoice
        {
            use HasTimestamps;

            private const int PRECISION = 2;

            public static int $issued = 0;

            public string $number = 'inv-1';

            protected string $currency = 'EUR';

            private array $lines = [];

            public string $reference {
                get => strtoupper($this->number);
            }

            public function __construct() {}

            public function issue(): void
            {
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_members_of_the_same_rank_in_the_author_s_order(): void
    {
        // Two constants, two private fields — nothing says WHICH constant comes first.
        $code = <<<'PHP'
        <?php
        class Ledger
        {
            private const int PRECISION = 2;

            public const string CURRENCY = 'EUR';

            private array $entries = [];

            private int $count = 0;

            public function post(): void
            {
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_stray_below_a_method_to_the_other_layout_rule(): void
    {
        // `member-after-method` already reports this one; saying it twice helps nobody.
        $code = <<<'PHP'
        <?php
        class Cache
        {
            private array $store = [];

            public function flush(): void
            {
            }

            private const int TTL = 60;
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_flags_an_enum_case_declared_under_a_constant(): void
    {
        $code = <<<'PHP'
        <?php
        enum Status: string
        {
            const string DEFAULT = 'draft';

            case Draft = 'draft';

            public function label(): string
            {
                return $this->value;
            }
        }
        PHP;

        $this->assertSame(['Status'], $this->scopes($code));
    }
}
