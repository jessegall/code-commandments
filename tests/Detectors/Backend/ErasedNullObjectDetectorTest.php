<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ErasedNullObjectDetector;
use PHPUnit\Framework\TestCase;

final class ErasedNullObjectDetectorTest extends TestCase
{
    private const string BLANK = <<<'PHP'
    final readonly class EmptyString implements Stringable
    {
        public function __toString(): string
        {
            return '';
        }
    }
    PHP;

    public function test_flags_a_blank_null_object_defaulted_into_a_string_slot(): void
    {
        $blank = self::BLANK;

        $code = <<<PHP
        <?php
        {$blank}

        final class Turn
        {
            public function __construct(
                public readonly string \$complained = new EmptyString,
            ) {}
        }
        PHP;

        $this->assertSame([13], $this->lines($code));
    }

    public function test_flags_a_blank_null_object_returned_as_a_string(): void
    {
        $blank = self::BLANK;

        $code = <<<PHP
        <?php
        {$blank}

        final class Signer
        {
            public function whoami(): string
            {
                return new EmptyString;
            }
        }
        PHP;

        $this->assertSame([14], $this->lines($code));
    }

    public function test_does_not_flag_the_object_where_the_type_admits_it(): void
    {
        $blank = self::BLANK;

        $code = <<<PHP
        <?php
        {$blank}

        final class Turn
        {
            public function __construct(
                public readonly Stringable \$complained = new EmptyString,
            ) {}

            public function spoken(): Stringable
            {
                return new EmptyString;
            }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_does_not_flag_an_object_that_renders_a_real_value(): void
    {
        $code = <<<'PHP'
        <?php
        final readonly class DefaultCurrency implements Stringable
        {
            public function __toString(): string
            {
                return 'EUR';
            }
        }

        final class Price
        {
            public function __construct(
                public readonly string $currency = new DefaultCurrency,
            ) {}
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_does_not_guess_at_a_computed_rendering(): void
    {
        $code = <<<'PHP'
        <?php
        final readonly class Maybe implements Stringable
        {
            public function __construct(private string $value) {}

            public function __toString(): string
            {
                return $this->value === 'x' ? '' : $this->value;
            }
        }

        final class Holder
        {
            public function __construct(
                public readonly string $held = new Maybe('x'),
            ) {}
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    /**
     * @return list<int>
     */
    private function lines(string $code): array
    {
        $lines = array_map(
            static fn ($match): int => $match->line(),
            (new ErasedNullObjectDetector)->find(Codebase::fromString($code)),
        );

        sort($lines);

        return $lines;
    }
}
