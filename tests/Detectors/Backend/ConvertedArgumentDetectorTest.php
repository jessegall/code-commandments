<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ConvertedArgumentDetector;
use PHPUnit\Framework\TestCase;

/**
 * Requested in #495 — the caller translating a value into the form the callee asked for.
 */
final class ConvertedArgumentDetectorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function locations(string $code): array
    {
        return array_map(
            static fn ($m): string => $m->location(),
            (new ConvertedArgumentDetector)->find(Codebase::fromString($code)),
        );
    }

    public function test_flags_the_same_conversion_at_every_site_of_one_parameter(): void
    {
        $code = <<<'PHP'
        <?php
        final class ClassAlias {
            public static function of(string $class): string { return strtolower($class); }
        }
        final class Raises {
            public static function of(string $signal, string $target): self { return new self(); }
        }
        final class BindSignals {
            public function bind(string $interaction, string $node): Raises {
                return Raises::of(ClassAlias::of($interaction), $node);
            }
        }
        final class BindHotkeys {
            public function bind(string $hotkey, string $node): Raises {
                return Raises::of(ClassAlias::of($hotkey), $node);
            }
        }
        PHP;

        $this->assertCount(2, $this->locations($code));
    }

    public function test_does_not_flag_a_conversion_written_once(): void
    {
        // One site is a one-off, not a parameter declared in the wrong currency.
        $code = <<<'PHP'
        <?php
        final class ClassAlias {
            public static function of(string $class): string { return strtolower($class); }
        }
        final class Raises {
            public static function of(string $signal, string $target): self { return new self(); }
        }
        final class BindSignals {
            public function bind(string $interaction, string $node): Raises {
                return Raises::of(ClassAlias::of($interaction), $node);
            }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }

    public function test_does_not_flag_a_conversion_the_parameter_rarely_gets(): void
    {
        // Two of six: the parameter plainly takes the raw form and those two convert for their own
        // reasons. Widening it would be wrong.
        $code = <<<'PHP'
        <?php
        final class Token {
            public static function of(string $raw): string { return $raw; }
        }
        final class Types {
            public static function parse(string $token): self { return new self(); }
        }
        final class Callers {
            public function a(string $x): Types { return Types::parse(Token::of($x)); }
            public function b(string $x): Types { return Types::parse(Token::of($x)); }
            public function c(string $x): Types { return Types::parse($x); }
            public function d(string $x): Types { return Types::parse($x); }
            public function e(string $x): Types { return Types::parse($x); }
            public function f(string $x): Types { return Types::parse($x); }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }

    public function test_does_not_flag_a_parameter_that_already_takes_the_object(): void
    {
        // `Category::from($raw)` filling a `Category` parameter is a boundary DECODE. Moving it inside
        // would make the callee take a string — the rule inverted.
        $code = <<<'PHP'
        <?php
        final class Category {
            public static function from(string $raw): self { return new self(); }
        }
        final class Shelf {
            public static function of(Category $category, string $label): self { return new self(); }
        }
        final class Callers {
            public function a(string $x): Shelf { return Shelf::of(Category::from($x), 'a'); }
            public function b(string $x): Shelf { return Shelf::of(Category::from($x), 'b'); }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }

    public function test_does_not_flag_the_callers_own_helper(): void
    {
        // `self::token(...)` means a different class inside the callee, and is usually private to this
        // one — the conversion cannot move.
        $code = <<<'PHP'
        <?php
        final class Types {
            public static function parse(string $token): self { return new self(); }
        }
        final class Callers {
            private static function token(string $x): string { return $x; }
            public function a(string $x): Types { return Types::parse(self::token($x)); }
            public function b(string $x): Types { return Types::parse(self::token($x)); }
        }
        PHP;

        $this->assertSame([], $this->locations($code));
    }
}
