<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\BlankStringDefaultDetector;
use PHPUnit\Framework\TestCase;

final class BlankStringDefaultDetectorTest extends TestCase
{
    public function test_flags_a_promoted_parameter_read_back_as_missing(): void
    {
        $code = <<<'PHP'
        <?php
        final class EmptyState
        {
            public function __construct(
                private readonly string $title,
                private readonly string $description = '',
            ) {}

            public function render(): string
            {
                return $this->description === '' ? $this->title : $this->title.$this->description;
            }
        }
        PHP;

        $this->assertSame([6], $this->lines($code));
    }

    public function test_flags_a_declared_property_and_a_plain_parameter(): void
    {
        $code = <<<'PHP'
        <?php
        final class Row
        {
            private string $duration = '';

            public function label(string $suffix = ''): string
            {
                if ($suffix === '') {
                    return $this->duration;
                }

                return $this->duration.$suffix;
            }

            public function mono(): string
            {
                return empty($this->duration) ? 'n/a' : $this->duration;
            }
        }
        PHP;

        $this->assertSame([4, 6], $this->lines($code));
    }

    public function test_does_not_flag_an_accumulator_or_a_joiner(): void
    {
        $code = <<<'PHP'
        <?php
        final class Writer
        {
            private string $buffer = '';

            public function write(string $line, string $glue = ''): void
            {
                $this->buffer .= $glue.$line;
            }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_does_not_flag_a_real_default_or_an_honest_nullable(): void
    {
        $code = <<<'PHP'
        <?php
        final class Money
        {
            public function __construct(
                private readonly string $currency = 'EUR',
                private readonly ?string $note = null,
            ) {}

            public function label(): string
            {
                return $this->currency === '' ? '?' : $this->currency;
            }

            public function note(): string
            {
                return $this->note ?? '';
            }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_does_not_flag_a_blank_default_questioned_in_another_scope(): void
    {
        $code = <<<'PHP'
        <?php
        final class Forwarder
        {
            public function of(string $hint = ''): string
            {
                return $this->wrap($hint);
            }

            public function wrap(string $hint): string
            {
                return $hint === '' ? 'none' : $hint;
            }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_does_not_read_a_local_as_evidence_about_a_property_of_the_same_name(): void
    {
        $code = <<<'PHP'
        <?php
        final class Element
        {
            public function __construct(
                public readonly string $tag,
                public readonly string $text = '',
            ) {}

            public function canonical(): string
            {
                $text = trim($this->text);

                return $text === '' ? '' : "T:{$text}";
            }
        }
        PHP;

        $this->assertSame([], $this->lines($code));
    }

    public function test_wrapping_the_blank_in_a_null_object_does_not_dodge_the_rule(): void
    {
        $code = <<<'PHP'
        <?php
        final readonly class EmptyString implements Stringable
        {
            public function __toString(): string
            {
                return '';
            }
        }

        final class EmptyState
        {
            public function __construct(
                private readonly string $title,
                private readonly string $description = new EmptyString,
            ) {}

            public function render(): string
            {
                return $this->description === '' ? $this->title : $this->description;
            }
        }
        PHP;

        $this->assertSame([14], $this->lines($code));
    }

    public function test_sees_the_question_asked_through_a_blankness_predicate(): void
    {
        $code = <<<'PHP'
        <?php
        final readonly class EmptyString implements Stringable
        {
            public function __toString(): string
            {
                return '';
            }

            public static function is(mixed $value): bool
            {
                return match (true) {
                    is_string($value) => $value === '',
                    $value instanceof Stringable => (string) $value === '',
                    default => false,
                };
            }

            public static function isNot(mixed $value): bool
            {
                return ! self::is($value);
            }
        }

        final class Binding
        {
            public function __construct(
                private readonly string $slug = new EmptyString,
                private readonly string $plane = '',
            ) {}

            public function isBound(): bool
            {
                return EmptyString::isNot($this->slug);
            }

            public function isPlaced(): bool
            {
                return EmptyString::is($this->plane);
            }
        }
        PHP;

        $this->assertSame([27, 28], $this->lines($code));
    }

    public function test_does_not_treat_an_ordinary_static_call_as_a_question(): void
    {
        $code = <<<'PHP'
        <?php
        final class Slug
        {
            public static function of(string $value): string
            {
                return strtolower($value);
            }
        }

        final class Page
        {
            public function __construct(
                private readonly string $title = '',
            ) {}

            public function slug(): string
            {
                return Slug::of($this->title);
            }
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
            (new BlankStringDefaultDetector)->find(Codebase::fromString($code)),
        );

        sort($lines);

        return $lines;
    }
}
