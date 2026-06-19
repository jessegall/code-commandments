<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoRawLiteralProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoRawLiteralProphetTest extends TestCase
{
    private NoRawLiteralProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoRawLiteralProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Literals
    // ────────────────────────────────────────────────────────────────

    public function test_flags_empty_string_literal_in_value_position(): void
    {
        $judgment = $this->judgeBody("return '';");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Raw empty string literal', $judgment->sins[0]->message);
        $this->assertStringContainsString('T_String::empty()', $judgment->sins[0]->suggestion);
    }

    public function test_t_helper_rewrites_are_type_checked_against_the_operand(): void
    {
        // #79/#83: one STRICT operand guard at the rewrite site — a T_* helper is
        // rewritten ONLY when the operand is provably its exact type; nullable /
        // object / a different type / UNRESOLVED operands are all left untouched,
        // so the helper is never handed a value its signature rejects.
        $repent = function (string $body): string {
            $src = "<?php\nclass C {\n{$body}\n}\n";

            return (string) ($this->prophet->repent('/x.php', $src)->newContent ?? $src);
        };

        // nullable -> T_String::isEmpty(string) would TypeError: leave it.
        $this->assertStringContainsString('return $x === "";', $repent(' public function a(?string $x): bool { return $x === ""; }'));
        // a DataCollection -> T_Array::coalesce(?array) would TypeError: leave it.
        $this->assertStringContainsString('return $this->c ?? [];', $repent(' public \Spatie\LaravelData\DataCollection $c; public function b(): array { return $this->c ?? []; }'));
        // an unresolved operand (untyped local / method result) — left, fail-safe.
        $this->assertStringContainsString('return $x === "";', $repent(' public function d($x): bool { return $x === ""; }'));

        // provably-string operand: rewritten.
        $this->assertStringContainsString('T_String::isEmpty($x)', $repent(' public function c(string $x): bool { return $x === ""; }'));
    }

    public function test_coalesce_rewrite_drops_a_null_default_but_keeps_a_real_one(): void
    {
        // #67: `(string)($x ?? null)` -> coalesce($x ?? null) — never `, null`,
        // since coalesce()'s $default is typed non-null (the cast makes null the
        // type's empty anyway). A real default is still carried through.
        $nullable = $this->prophet->repent('/x.php', "<?php\nreturn (string)(\$raw['label'] ?? null);\n");
        $this->assertStringContainsString("T_String::coalesce(\$raw['label'] ?? null);", $nullable->newContent);
        $this->assertStringNotContainsString(', null)', $nullable->newContent);

        $withDefault = $this->prophet->repent('/x.php', "<?php\nreturn (string)(\$x ?? 'fallback');\n");
        $this->assertStringContainsString("T_String::coalesce(\$x, 'fallback');", $withDefault->newContent);
    }

    public function test_only_rewrites_int_zero_compare_when_the_operand_is_int(): void
    {
        // #56: T_Int::isZero(int) is strict — never rewrite `$x === T_Int::ZERO`
        // to it on a mixed/string operand (TypeError), only on a provably-int one.
        $src = "<?php\nnamespace App;\nuse JesseGall\\PhpTypes\\T_Int;\nclass C {\n"
            . " public function m(mixed \$a): bool { return \$a === T_Int::ZERO; }\n"
            . " public function s(string \$b): bool { return \$b === T_Int::ZERO; }\n"
            . " public function i(int \$c): bool { return \$c === T_Int::ZERO; }\n"
            . "}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertStringContainsString('return $a === T_Int::ZERO;', $result->newContent, 'mixed operand left alone');
        $this->assertStringContainsString('return $b === T_Int::ZERO;', $result->newContent, 'string operand left alone');
        $this->assertStringContainsString('return T_Int::isZero($c);', $result->newContent, 'int operand rewritten');
    }

    public function test_flags_double_quoted_empty_string(): void
    {
        $judgment = $this->judgeBody('return "";');

        $this->assertFallen($judgment, 1);
    }

    public function test_suggests_constant_form_in_parameter_default(): void
    {
        $content = $this->wrap("public function f(string \$x = ''): void {}");

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::EMPTY', $judgment->sins[0]->suggestion);
        $this->assertStringContainsString('constant position', $judgment->sins[0]->suggestion);
    }

    public function test_suggests_constant_form_in_property_default(): void
    {
        $content = $this->wrap("private string \$name = '';");

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::EMPTY', $judgment->sins[0]->suggestion);
    }

    public function test_flags_empty_json_object_literal(): void
    {
        $judgment = $this->judgeBody("return '{}';");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Json::emptyObject()', $judgment->sins[0]->suggestion);
    }

    public function test_flags_empty_json_array_literal(): void
    {
        $judgment = $this->judgeBody("return '[]';");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Json::emptyArray()', $judgment->sins[0]->suggestion);
    }

    // ────────────────────────────────────────────────────────────────
    // Comparisons
    // ────────────────────────────────────────────────────────────────

    public function test_flags_identical_empty_comparison(): void
    {
        $judgment = $this->judgeBody("if (\$this->name === '') { return; }");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::isEmpty($this->name)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_not_identical_empty_comparison(): void
    {
        $judgment = $this->judgeBody("if (\$this->name !== '') { return; }");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::isNotEmpty($this->name)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_reversed_operand_order(): void
    {
        $judgment = $this->judgeBody("if ('' === \$this->name) { return; }");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::isEmpty($this->name)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_strlen_zero_comparison(): void
    {
        $judgment = $this->judgeBody("if (strlen(\$this->name) === 0) { return; }");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::isEmpty($this->name)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_strlen_greater_than_zero_as_not_empty(): void
    {
        $judgment = $this->judgeBody("if (strlen(\$this->name) > 0) { return; }");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::isNotEmpty($this->name)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_trim_comparison_as_blank(): void
    {
        $judgment = $this->judgeBody("if (trim(\$this->name) === '') { return; }");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::isBlank($this->name)', $judgment->sins[0]->suggestion);
    }

    public function test_custom_trim_charlist_is_plain_emptiness_not_blank(): void
    {
        $judgment = $this->judgeBody("if (trim(\$this->name, '/') === '') { return; }");

        $this->assertFallen($judgment);
        $this->assertStringContainsString('isEmpty(trim($this->name', $judgment->sins[0]->suggestion);
    }

    public function test_flags_json_object_comparison(): void
    {
        $judgment = $this->judgeBody("if (\$this->payload === '{}') { return; }");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Json::isEmptyObject($this->payload)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_comparison_against_t_array_empty_factory(): void
    {
        $judgment = $this->judgeBody('return $this->items === \JesseGall\PhpTypes\T_Array::empty() ? 1 : 2;');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Array::isEmpty($this->items)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_comparison_against_t_array_empty_constant(): void
    {
        $judgment = $this->judgeBody('return $this->items === \JesseGall\PhpTypes\T_Array::EMPTY ? 1 : 2;');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Array::isEmpty($this->items)', $judgment->sins[0]->suggestion);
    }

    public function test_flags_helper_comparison_for_other_kinds(): void
    {
        // #56: the int/float zero predicates are strictly typed, so they only
        // fire on a provably int/float operand (here, a typed param).
        $int = $this->prophet->judge('/x.php', "<?php\nclass C { public function m(int \$n): bool { return \$n === \\JesseGall\\PhpTypes\\T_Int::ZERO; } }");
        $this->assertStringContainsString('T_Int::isZero($n)', $int->sins[0]->suggestion);

        $float = $this->prophet->judge('/x.php', "<?php\nclass C { public function m(float \$f): bool { return \$f === \\JesseGall\\PhpTypes\\T_Float::ZERO; } }");
        $this->assertStringContainsString('T_Float::isZero($f)', $float->sins[0]->suggestion);

        // #83: bool / json predicates are type-gated too — only on a provably
        // bool / string operand (a typed param here).
        $bool = $this->prophet->judge('/x.php', "<?php\nclass C { public function m(bool \$b): bool { return \$b === \\JesseGall\\PhpTypes\\T_Bool::TRUE; } }");
        $this->assertStringContainsString('T_Bool::isTrue($b)', $bool->sins[0]->suggestion);

        $json = $this->prophet->judge('/x.php', "<?php\nclass C { public function m(string \$j): bool { return \$j === \\JesseGall\\PhpTypes\\T_Json::EMPTY_OBJECT; } }");
        $this->assertStringContainsString('T_Json::isEmptyObject($j)', $json->sins[0]->suggestion);
    }

    public function test_helper_comparison_negation_uses_inverse_predicate(): void
    {
        $array = $this->prophet->judge('/x.php', "<?php\nclass C { public function m(array \$a): bool { return \$a !== \\JesseGall\\PhpTypes\\T_Array::empty(); } }");
        $this->assertStringContainsString('T_Array::isNotEmpty($a)', $array->sins[0]->suggestion);

        $bool = $this->prophet->judge('/x.php', "<?php\nclass C { public function m(bool \$b): bool { return \$b !== \\JesseGall\\PhpTypes\\T_Bool::TRUE; } }");
        $this->assertStringContainsString('T_Bool::isFalse($b)', $bool->sins[0]->suggestion);
    }

    public function test_helper_comparison_negation_without_inverse_uses_bang(): void
    {
        $json = $this->prophet->judge('/x.php', "<?php\nclass C { public function m(string \$j): bool { return \$j !== \\JesseGall\\PhpTypes\\T_Json::EMPTY_OBJECT; } }");
        $this->assertStringContainsString('! T_Json::isEmptyObject($j)', $json->sins[0]->suggestion);
    }

    public function test_repent_rewrites_helper_comparison(): void
    {
        $result = $this->prophet->repent('/x.php', $this->wrap('public function f(array $added): bool { return $added === \JesseGall\PhpTypes\T_Array::empty(); }'));

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_Array::isEmpty($added);', $result->newContent);
    }

    public function test_flags_negated_json_array_comparison(): void
    {
        $judgment = $this->judgeBody("if (\$this->payload !== '[]') { return; }");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('! T_Json::isEmptyArray($this->payload)', $judgment->sins[0]->suggestion);
    }

    // ────────────────────────────────────────────────────────────────
    // Non-flagging
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_non_empty_string(): void
    {
        $this->assertTrue($this->judgeBody("return 'hello';")->isRighteous());
    }

    public function test_does_not_flag_other_strlen_comparison(): void
    {
        $this->assertTrue($this->judgeBody("if (strlen(\$this->name) === 5) { return; }")->isRighteous());
    }

    public function test_does_not_flag_empty_array_by_default(): void
    {
        $this->assertTrue($this->judgeBody("return [];")->isRighteous());
    }

    public function test_flags_empty_array_when_configured(): void
    {
        $this->prophet->configure(['flag_empty_array' => true]);

        $judgment = $this->judgeBody("return [];");
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Array::empty()', $judgment->sins[0]->suggestion);
    }

    public function test_flags_nested_matrix_literal_by_default(): void
    {
        $judgment = $this->judgeBody("return [[]];");

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('Raw nested-array literal `[[]]`', $judgment->sins[0]->message);
        $this->assertStringContainsString('T_Array::matrix()', $judgment->sins[0]->suggestion);
    }

    public function test_matrix_in_property_default_suggests_constant(): void
    {
        $content = $this->wrap("private array \$frames = [[]];");

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Array::MATRIX', $judgment->sins[0]->suggestion);
    }

    public function test_does_not_flag_nested_array_with_content(): void
    {
        $this->assertTrue($this->judgeBody("return [['a']];")->isRighteous());
        $this->assertTrue($this->judgeBody("return [[], []];")->isRighteous());
    }

    public function test_collapses_half_converted_matrix_constant_form(): void
    {
        // The artifact a prior empty-array fix leaves behind.
        $judgment = $this->judgeBody('return [T_Array::EMPTY];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Array::matrix()', $judgment->sins[0]->suggestion);
    }

    public function test_collapses_half_converted_matrix_factory_form(): void
    {
        $judgment = $this->judgeBody('return [T_Array::empty()];');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Array::matrix()', $judgment->sins[0]->suggestion);
    }

    public function test_matrix_takes_precedence_over_inner_empty_array(): void
    {
        // Even with empty-array flagging on, [[]] yields ONE matrix finding,
        // not a matrix plus the inner [].
        $this->prophet->configure(['flag_empty_array' => true]);

        $judgment = $this->judgeBody("return [[]];");
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('matrix', $judgment->sins[0]->suggestion);
    }

    public function test_repent_rewrites_matrix_to_factory_with_import(): void
    {
        $content = $this->wrap("public function f(): array { return [[]]; }");

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_Array::matrix();', $result->newContent);
        $this->assertStringContainsString('use JesseGall\PhpTypes\T_Array;', $result->newContent);
    }

    public function test_repent_rewrites_matrix_property_default_to_constant(): void
    {
        $content = $this->wrap("private array \$frames = [[]];");

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('$frames = T_Array::MATRIX;', $result->newContent);
    }

    public function test_does_not_flag_the_type_helper_class_itself(): void
    {
        $content = <<<'PHP'
        <?php
        namespace JesseGall\PhpTypes;
        final class T_String {
            public const EMPTY = '';
            public static function isEmpty(string $value): bool {
                return $value === '';
            }
        }
        PHP;

        $this->assertTrue($this->prophet->judge('/x.php', $content)->isRighteous());
    }

    public function test_handles_invalid_php_gracefully(): void
    {
        $this->assertTrue($this->prophet->judge('/x.php', '<?php this is not <<< valid')->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Auto-fix (repent)
    // ────────────────────────────────────────────────────────────────

    public function test_is_auto_fixable(): void
    {
        $this->assertInstanceOf(
            \JesseGall\CodeCommandments\Contracts\SinRepenter::class,
            $this->prophet,
        );
    }

    public function test_repent_rewrites_literal_and_adds_import(): void
    {
        $content = $this->wrap("public function f(): string { return ''; }");

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_String::empty();', $result->newContent);
        $this->assertStringContainsString('use JesseGall\PhpTypes\T_String;', $result->newContent);
    }

    public function test_repent_rewrites_comparison(): void
    {
        $content = $this->wrap("public function f(): bool { return \$this->name === ''; }");

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_String::isEmpty($this->name);', $result->newContent);
    }

    public function test_repent_uses_constant_in_parameter_default(): void
    {
        $content = $this->wrap("public function f(string \$x = ''): void {}");

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString("\$x = T_String::EMPTY", $result->newContent);
    }

    public function test_repent_rewrites_trim_to_blank_with_inner_arg(): void
    {
        $content = $this->wrap("public function f(): bool { return trim(\$this->name) === ''; }");

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_String::isBlank($this->name);', $result->newContent);
    }

    public function test_repent_imports_each_class_once_and_result_reparses(): void
    {
        $content = $this->wrap("public function f(): array { return [\$this->a === '', '{}', '']; }");

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertSame(1, substr_count($result->newContent, 'use JesseGall\PhpTypes\T_String;'));
        $this->assertSame(1, substr_count($result->newContent, 'use JesseGall\PhpTypes\T_Json;'));

        // The rewritten file must still parse.
        $ast = (new \PhpParser\ParserFactory)->createForNewestSupportedVersion()->parse($result->newContent);
        $this->assertNotNull($ast);
    }

    public function test_repent_reuses_existing_alias(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        use JesseGall\PhpTypes\T_String as Str;
        final class Spec {
            public function f(): string { return ''; }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);
        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return Str::empty();', $result->newContent);
        $this->assertSame(1, substr_count($result->newContent, 'use JesseGall\PhpTypes\T_String'));
    }

    // ────────────────────────────────────────────────────────────────
    // Description sanity
    // ────────────────────────────────────────────────────────────────

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertStringContainsString('T_String', $this->prophet->detailedDescription());
        $this->assertStringContainsString('isBlank', $this->prophet->detailedDescription());
        $this->assertStringContainsString('parse, don', $this->prophet->detailedDescription());
    }

    // ────────────────────────────────────────────────────────────────
    // Whitespace / control literals (on by default)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_newline_literal_by_default(): void
    {
        $judgment = $this->judgeBody('return "\n";');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::NEWLINE', $judgment->sins[0]->suggestion);
    }

    public function test_flags_paragraph_literal(): void
    {
        $judgment = $this->judgeBody('return "\n\n";');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::PARAGRAPH', $judgment->sins[0]->suggestion);
    }

    public function test_flags_tab_cr_crlf_and_null_byte(): void
    {
        $this->assertStringContainsString('T_String::TAB', $this->judgeBody('return "\t";')->sins[0]->suggestion);
        $this->assertStringContainsString('T_String::CARRIAGE_RETURN', $this->judgeBody('return "\r";')->sins[0]->suggestion);
        $this->assertStringContainsString('T_String::CRLF', $this->judgeBody('return "\r\n";')->sins[0]->suggestion);
        $this->assertStringContainsString('T_String::NULL_BYTE', $this->judgeBody('return "\0";')->sins[0]->suggestion);
    }

    public function test_does_not_flag_string_with_content_containing_newline(): void
    {
        $this->assertTrue($this->judgeBody('return "hello\nworld";')->isRighteous());
    }

    public function test_whitespace_can_be_disabled(): void
    {
        $this->prophet->configure(['flag_whitespace' => false]);

        $this->assertTrue($this->judgeBody('return "\n";')->isRighteous());
    }

    public function test_repent_rewrites_newline_with_import(): void
    {
        $result = $this->prophet->repent('/x.php', $this->wrap('public function f(): string { return "\n"; }'));

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_String::NEWLINE;', $result->newContent);
        $this->assertStringContainsString('use JesseGall\PhpTypes\T_String;', $result->newContent);
    }

    // ────────────────────────────────────────────────────────────────
    // Opt-in: space, separators, sentinel ints
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_space_or_separators_or_ints_by_default(): void
    {
        $this->assertTrue($this->judgeBody('return " ";')->isRighteous());
        $this->assertTrue($this->judgeBody('return ",";')->isRighteous());
        $this->assertTrue($this->judgeBody('return 1;')->isRighteous());
        $this->assertTrue($this->judgeBody('return -1;')->isRighteous());
    }

    public function test_flags_space_when_enabled(): void
    {
        $this->prophet->configure(['flag_space' => true]);

        $judgment = $this->judgeBody('return " ";');
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_String::SPACE', $judgment->sins[0]->suggestion);
    }

    public function test_flags_separators_when_enabled(): void
    {
        $this->prophet->configure(['flag_separators' => true]);

        $this->assertStringContainsString('T_String::COMMA_SPACE', $this->judgeBody('return ", ";')->sins[0]->suggestion);
        $this->assertStringContainsString('T_String::SLASH', $this->judgeBody('return "/";')->sins[0]->suggestion);
    }

    public function test_flags_sentinel_ints_when_enabled(): void
    {
        $this->prophet->configure(['flag_sentinel_ints' => true]);

        $this->assertStringContainsString('T_Int::ZERO', $this->judgeBody('return 0;')->sins[0]->suggestion);
        $this->assertStringContainsString('T_Int::ONE', $this->judgeBody('return 1;')->sins[0]->suggestion);
        $this->assertStringContainsString('T_Int::MINUS_ONE', $this->judgeBody('return -1;')->sins[0]->suggestion);
    }

    public function test_flags_float_zero_when_enabled(): void
    {
        $this->prophet->configure(['flag_sentinel_floats' => true]);

        $judgment = $this->judgeBody('return 0.0;');
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Float::ZERO', $judgment->sins[0]->suggestion);
    }

    public function test_does_not_flag_float_zero_by_default(): void
    {
        $this->assertTrue($this->judgeBody('return 0.0;')->isRighteous());
    }

    public function test_repent_rewrites_float_zero_with_t_float_import(): void
    {
        $this->prophet->configure(['flag_sentinel_floats' => true]);

        $result = $this->prophet->repent('/x.php', $this->wrap('public function f(): float { return 0.0; }'));

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_Float::ZERO;', $result->newContent);
        $this->assertStringContainsString('use JesseGall\PhpTypes\T_Float;', $result->newContent);
    }

    public function test_minus_one_is_one_finding_not_a_nested_one(): void
    {
        $this->prophet->configure(['flag_sentinel_ints' => true]);

        $this->assertFallen($this->judgeBody('return -1;'), 1);
    }

    public function test_does_not_flag_int_inside_declare(): void
    {
        $this->prophet->configure(['flag_sentinel_ints' => true]);

        $content = <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace App;
        final class Spec {
            public function f(): int { return 1; }
        }
        PHP;

        // The `1` in declare() is illegal as a constant — only the return's `1` flags.
        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('T_Int::ONE', $judgment->sins[0]->suggestion);
    }

    public function test_repent_rewrites_minus_one_with_t_int_import(): void
    {
        $this->prophet->configure(['flag_sentinel_ints' => true]);

        $result = $this->prophet->repent('/x.php', $this->wrap('public function f(): int { return -1; }'));

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return T_Int::MINUS_ONE;', $result->newContent);
        $this->assertStringContainsString('use JesseGall\PhpTypes\T_Int;', $result->newContent);
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    public function test_flags_cast_coalesce_to_empty_toward_coalesce_helper(): void
    {
        $judgment = $this->judgeBody('return (string) ($this->value ?? "");');

        $this->assertTrue($judgment->isFallen());
        $messages = implode("\n", array_map(fn ($s) => $s->message, $judgment->sins));
        $this->assertStringContainsString('T_String::coalesce', $messages);
    }

    public function test_repent_rewrites_coalesce_to_helper(): void
    {
        $src = "<?php\nnamespace App;\nfinal class Spec {\n    public function a(\$x): string { return (string) (\$x ?? \"\"); }\n}\n";
        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('T_String::coalesce($x)', $result->newContent);
        $this->assertStringContainsString('use JesseGall\\PhpTypes\\T_String;', $result->newContent);
    }

    public function test_coalesce_carries_non_empty_fallback_as_default(): void
    {
        // `$x ?? T_String::COMMA` is a real default (',') — the rewrite must
        // carry it through as coalesce()'s second arg, never drop it to ''.
        $src = "<?php\nnamespace App;\nuse JesseGall\\PhpTypes\\T_String;\nfinal class Spec {\n    public function a(\$x): string { return (string) (\$x ?? T_String::COMMA); }\n}\n";
        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('T_String::coalesce($x, T_String::COMMA)', $result->newContent);
    }

    public function test_coalesce_leaves_an_array_element_of_unprovable_type(): void
    {
        // #83: `$arr[$k]` is a native-array ELEMENT — statically `mixed`, so it
        // cannot be proven `?array`. T_Array::coalesce(?array) would TypeError on a
        // non-array element, so the rewrite is withheld (fail-safe), keeping the
        // original `?? T_Array::empty()`.
        $src = "<?php\nnamespace App;\nuse JesseGall\\PhpTypes\\T_Array;\nfinal class Spec {\n    public function a(array \$arr, \$k): array { return \$arr[\$k] ?? T_Array::empty(); }\n}\n";
        $result = $this->prophet->repent('/x.php', $src);

        $this->assertStringNotContainsString('T_Array::coalesce', (string) ($result->newContent ?? $src));
    }

    private function judgeBody(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', $this->wrap("public function run(): mixed { {$body} }"));
    }

    private function wrap(string $members): string
    {
        // Typed operand properties so the #79/#83 strict guard can resolve them
        // ($this->name is provably a string, $this->items provably an array, …).
        return <<<PHP
        <?php
        namespace App;
        final class Spec {
            public string \$name;
            public string \$payload;
            public int \$count;
            public array \$items;
            {$members}
        }
        PHP;
    }

    private function assertFallen(Judgment $judgment, ?int $expectedSins = null): void
    {
        $this->assertTrue(
            $judgment->isFallen(),
            'Expected judgment to be fallen. Sins: ' . json_encode(array_map(
                fn ($s) => $s->message,
                $judgment->sins,
            ))
        );

        if ($expectedSins !== null) {
            $this->assertCount(
                $expectedSins,
                $judgment->sins,
                'Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
            );
        }
    }
}
