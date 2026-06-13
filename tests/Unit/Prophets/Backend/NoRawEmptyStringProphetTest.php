<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoRawEmptyStringProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoRawEmptyStringProphetTest extends TestCase
{
    private NoRawEmptyStringProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoRawEmptyStringProphet();
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
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function judgeBody(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', $this->wrap("public function run(): mixed { {$body} }"));
    }

    private function wrap(string $members): string
    {
        return <<<PHP
        <?php
        namespace App;
        final class Spec {
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
