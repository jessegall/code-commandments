<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\MemberOutOfOrderDetector;
use JesseGall\CodeCommandments\Scribes\Backend\MemberOutOfOrderScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class MemberOutOfOrderScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new MemberOutOfOrderDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new MemberOutOfOrderScribe();
    }

    public function test_sorts_the_head_into_the_canonical_order(): void
    {
        $php = <<<'PHP'
        <?php

        class Invoice
        {
            private array $lines = [];

            public string $reference {
                get => strtoupper($this->number);
            }

            public static int $issued = 0;

            /** Cents, always. */
            private const int PRECISION = 2;

            public string $number = 'inv-1';

            public function issue(): void
            {
            }
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Invoice
        {
            /** Cents, always. */
            private const int PRECISION = 2;

            public static int $issued = 0;

            public string $number = 'inv-1';

            private array $lines = [];

            public string $reference {
                get => strtoupper($this->number);
            }

            public function issue(): void
            {
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_keeps_a_tight_group_tight_and_a_spaced_one_spaced(): void
    {
        $php = <<<'PHP'
        <?php

        class Wire
        {
            private string $socket = '';
            private int $retries = 0;

            public const string VERSION = '2';

            public function open(): void
            {
            }
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Wire
        {
            public const string VERSION = '2';

            private string $socket = '';
            private int $retries = 0;

            public function open(): void
            {
            }
        }
        PHP;

        $this->assertSame($expected, $this->fixStable($php));
    }

    public function test_leaves_a_head_that_is_already_canonical_untouched(): void
    {
        $php = <<<'PHP'
        <?php

        class Ledger
        {
            private const int PRECISION = 2;

            public string $currency = 'EUR';

            private array $entries = [];

            public function post(): void
            {
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
        $this->assertSame($php, $this->fix($php));
    }

    public function test_moves_only_what_is_misplaced_and_is_idempotent(): void
    {
        $php = <<<'PHP'
        <?php

        class Roster
        {
            public const string ROLE = 'driver';

            private array $names = [];

            protected int $shift = 0;

            public function add(): void
            {
            }
        }
        PHP;

        $expected = <<<'PHP'
        <?php

        class Roster
        {
            public const string ROLE = 'driver';

            protected int $shift = 0;

            private array $names = [];

            public function add(): void
            {
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertSame($expected, $fixed);
        $this->assertSame([], $this->findings($fixed), 'the sin no longer fires');
        $this->assertSame($fixed, $this->fix($fixed), 'a second pass changes nothing');
    }
}
