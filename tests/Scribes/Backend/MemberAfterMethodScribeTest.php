<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\MemberAfterMethodDetector;
use JesseGall\CodeCommandments\Scribes\Backend\MemberAfterMethodScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class MemberAfterMethodScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new MemberAfterMethodDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new MemberAfterMethodScribe();
    }

    public function test_hoists_a_stray_property_above_the_first_method(): void
    {
        $php = <<<'PHP'
        <?php

        class Cart
        {
            public function add(string $sku): void
            {
                $this->lines[] = $sku;
            }

            /** @var list<string> */
            private array $lines = [];
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Cart
        {
            /** @var list<string> */
            private array $lines = [];

            public function add(string $sku): void
            {
                $this->lines[] = $sku;
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_carries_the_docblock_and_lands_under_the_state_already_at_the_head(): void
    {
        $php = <<<'PHP'
        <?php

        class Retry
        {
            private int $attempts = 0;

            public function attempt(): int
            {
                return $this->attempts < self::MAX ? ++$this->attempts : self::MAX;
            }

            /** How many times the gateway tolerates the same idempotency key. */
            private const int MAX = 3;
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Retry
        {
            private int $attempts = 0;

            /** How many times the gateway tolerates the same idempotency key. */
            private const int MAX = 3;

            public function attempt(): int
            {
                return $this->attempts < self::MAX ? ++$this->attempts : self::MAX;
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_a_run_split_around_a_method_keeps_its_original_order(): void
    {
        $php = <<<'PHP'
        <?php

        class Wire
        {
            public const string FIRST = 'a';

            public function render(): string
            {
                return self::FIRST . self::SECOND . self::THIRD;
            }

            public const string SECOND = 'b';

            public const string THIRD = 'c';
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Wire
        {
            public const string FIRST = 'a';

            public const string SECOND = 'b';

            public const string THIRD = 'c';

            public function render(): string
            {
                return self::FIRST . self::SECOND . self::THIRD;
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_does_not_touch_a_class_that_already_states_first(): void
    {
        $php = <<<'PHP'
        <?php

        class Ledger
        {
            private const int PRECISION = 2;

            private array $entries = [];

            public function __construct(private readonly Clock $clock) {}

            public function post(int $cents): void
            {
                $this->entries[] = $cents;
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
        $this->assertSame($php, $this->fix($php));
    }

    public function test_is_idempotent(): void
    {
        $php = <<<'PHP'
        <?php

        class Invoice
        {
            public function issue(): void
            {
            }

            private string $slug = 'inv';
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertSame([], $this->findings($fixed), 'the sin no longer fires');
        $this->assertSame($fixed, $this->fix($fixed), 'a second pass changes nothing');
    }
}
