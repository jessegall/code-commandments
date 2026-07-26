<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MemberAfterMethodDetector;
use PHPUnit\Framework\TestCase;

/**
 * A class states what it HAS before it says what it DOES: every constant, property and property hook
 * stands at the top, above the constructor. One hidden between two methods is state a reader only
 * meets by accident.
 */
final class MemberAfterMethodDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = (new MemberAfterMethodDetector)->find(Codebase::fromString($code));

        return array_map(static fn ($m): string => $m->scope(), $hits);
    }

    public function test_flags_a_property_declared_between_two_methods(): void
    {
        $code = <<<'PHP'
        <?php
        class Cart
        {
            public function add(string $sku): void
            {
                $this->lines[] = $sku;
            }

            /** @var list<string> */
            private array $lines = [];

            public function count(): int
            {
                return count($this->lines);
            }
        }
        PHP;

        $this->assertSame(['Cart'], $this->scopes($code));
    }

    public function test_flags_a_constant_declared_after_a_method(): void
    {
        $code = <<<'PHP'
        <?php
        class Retry
        {
            public function attempt(): int
            {
                return self::MAX;
            }

            private const int MAX = 3;
        }
        PHP;

        $this->assertSame(['Retry'], $this->scopes($code));
    }

    public function test_flags_a_hooked_property_declared_after_a_method(): void
    {
        $code = <<<'PHP'
        <?php
        class Invoice
        {
            public function issue(): void
            {
            }

            public string $reference {
                get => strtoupper($this->slug);
            }

            private string $slug = 'inv';
        }
        PHP;

        $this->assertSame(['Invoice', 'Invoice'], $this->scopes($code));
    }

    public function test_flags_a_trait_use_dropped_in_below_the_methods(): void
    {
        $code = <<<'PHP'
        <?php
        class Order
        {
            public function total(): int
            {
                return 0;
            }

            use HasTimestamps;
        }
        PHP;

        $this->assertSame(['Order'], $this->scopes($code));
    }

    public function test_flags_an_enum_case_declared_after_a_method(): void
    {
        $code = <<<'PHP'
        <?php
        enum Status: string
        {
            case Draft = 'draft';

            public function label(): string
            {
                return ucfirst($this->value);
            }

            case Settled = 'settled';
        }
        PHP;

        $this->assertSame(['Status'], $this->scopes($code));
    }

    public function test_leaves_a_class_that_states_everything_before_it_acts(): void
    {
        $code = <<<'PHP'
        <?php
        class Ledger
        {
            use HasTimestamps;

            private const int PRECISION = 2;

            /** @var list<int> */
            private array $entries = [];

            public string $summary {
                get => count($this->entries) . ' entries';
            }

            public function __construct(private readonly Clock $clock) {}

            public function post(int $cents): void
            {
                $this->entries[] = $cents;
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_promoted_constructor_property_alone(): void
    {
        // Promotion declares state IN the signature — the one place a field legitimately appears
        // alongside behaviour, and it is still above every method that uses it.
        $code = <<<'PHP'
        <?php
        class Mailer
        {
            public function __construct(private readonly Transport $transport) {}

            public function send(string $body): void
            {
                $this->transport->write($body);
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_leaves_a_method_declared_after_a_method(): void
    {
        $code = <<<'PHP'
        <?php
        class Pipeline
        {
            private array $stages = [];

            public function first(): void
            {
            }

            public function second(): void
            {
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }
}
