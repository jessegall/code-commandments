<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\UnnamedVocabularyLiteralDetector;
use PHPUnit\Framework\TestCase;

/**
 * The rule turns on EVIDENCE — a slot the codebase already spells by name — never on a literal
 * that merely equals some constant's value. These tests are mostly about what it must NOT flag.
 */
final class UnnamedVocabularyLiteralDetectorTest extends TestCase
{
    public function test_flags_a_literal_in_a_slot_the_codebase_spells_by_name(): void
    {
        $code = <<<'PHP'
        <?php
        class Token {
            public const string BRACE_OPEN = '{';
            public const string COLON = ':';
        }
        class Parser {
            public function parse(): void {
                $this->expect(Token::COLON);
                $this->expect('{');
            }
            private function expect(string $punct): void {}
        }
        PHP;

        $hits = new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($code));

        $this->assertSame(['Parser::parse'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_ignores_a_literal_whose_slot_is_never_spelled_by_name(): void
    {
        $code = <<<'PHP'
        <?php
        class Token {
            public const string COMMA = ',';
        }
        class Report {
            public function render(array $rows): string {
                return implode(',', $rows);
            }
        }
        PHP;

        // `implode`'s glue is never written as `Token::COMMA` anywhere, so the codebase has
        // decided nothing about that slot and there is no disagreement to report.
        $this->assertSame([], new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($code)));
    }

    public function test_ignores_the_constant_declaration_itself(): void
    {
        $code = <<<'PHP'
        <?php
        class Token {
            public const string BRACE_OPEN = '{';
            public const string COLON = ':';
        }
        class Parser {
            public function parse(): void { $this->expect(Token::COLON); }
            private function expect(string $punct): void {}
        }
        PHP;

        // The `'{'` in `const BRACE_OPEN = '{'` is the NAME being given, not a use of it.
        $this->assertSame([], new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($code)));
    }

    public function test_ignores_a_private_constant_which_is_not_shared_vocabulary(): void
    {
        $code = <<<'PHP'
        <?php
        class Token {
            private const string BRACE_OPEN = '{';
            private const string COLON = ':';
        }
        class Parser {
            public function parse(): void {
                $this->expect(Token::COLON);
                $this->expect('{');
            }
            private function expect(string $punct): void {}
        }
        PHP;

        $this->assertSame([], new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($code)));
    }

    public function test_does_not_confuse_two_classes_sharing_a_method_name(): void
    {
        $code = <<<'PHP'
        <?php
        class Token {
            public const string BRACE_OPEN = '{';
            public const string COLON = ':';
        }
        class Parser {
            public function parse(): void { $this->expect(Token::COLON); }
            private function expect(string $punct): void {}
        }
        class Validator {
            public function check(): void { $this->expect('{'); }
            private function expect(string $rule): void {}
        }
        PHP;

        // `Validator::expect` is a DIFFERENT slot from `Parser::expect`; nothing was decided
        // about it, so the literal stands.
        $this->assertSame([], new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($code)));
    }

    /**
     * The false positive this rule was calibrated against, kept as a test. In `jessegall/workflows`,
     * `TypeParser` splits a TYPE UNION with `explode(UnionType::SEPARATOR, …)` and
     * `CompiledSilhouettes` splits a CACHE KEY with `explode('|', $key, 2)`. Same character, two
     * unrelated concepts — and `explode`'s delimiter is a parameter every split in a codebase shares,
     * so it was never a decision the project made.
     */
    public function test_ignores_a_global_functions_parameter_which_every_caller_shares(): void
    {
        $code = <<<'PHP'
        <?php
        class UnionType {
            public const string SEPARATOR = '|';
        }
        class TypeParser {
            public function parse(string $token): array { return explode(UnionType::SEPARATOR, $token); }
        }
        class CompiledSilhouettes {
            public function shapesOf(string $key): array { return explode('|', $key, 2); }
        }
        PHP;

        $this->assertSame([], new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($code)));
    }

    public function test_ignores_a_numeric_or_empty_value_which_names_nothing(): void
    {
        $code = <<<'PHP'
        <?php
        class Limits {
            public const string NONE = '';
            public const string FIRST = '1';
            public const string TAG = 'tag';
        }
        class Runner {
            public function run(): void {
                $this->mark(Limits::TAG);
                $this->mark('');
                $this->mark('1');
            }
            private function mark(string $value): void {}
        }
        PHP;

        // `''` and `'1'` belong to every default and index test in a codebase — a constant
        // holding one must not claim them all.
        $this->assertSame([], new UnnamedVocabularyLiteralDetector()->find(Codebase::fromString($code)));
    }
}
