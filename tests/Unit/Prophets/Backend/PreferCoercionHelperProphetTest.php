<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferCoercionHelperProphet;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferCoercionHelperProphetTest extends TestCase
{
    private PreferCoercionHelperProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferCoercionHelperProphet();
    }

    public function test_repent_rewrites_exact_shapes_and_adds_imports(): void
    {
        // #90: the auto-fixable exact-semantic shapes become T_*::coerce/coerceOrNull.
        $src = "<?php\nnamespace App;\nclass C {\n"
            . " public function a(\$x) { return is_numeric(\$x) ? (int) \$x : 1; }\n"
            . " public function b(\$x) { return is_numeric(\$x) ? (int) \$x : null; }\n"
            . " public function c(\$x) { return is_scalar(\$x) ? (string) \$x : 'd'; }\n"
            . " public function d(\$x) { return is_scalar(\$x) ? (string) \$x : 'e'; }\n"
            . "}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('T_Int::coerce($x, 1)', $result->newContent);
        $this->assertStringContainsString('T_Int::coerceOrNull($x)', $result->newContent);
        $this->assertStringContainsString("T_String::coerce(\$x, 'd')", $result->newContent);
        $this->assertStringContainsString('use JesseGall\\PhpTypes\\T_Int;', $result->newContent);
        $this->assertStringContainsString('use JesseGall\\PhpTypes\\T_String;', $result->newContent);
    }

    public function test_repent_leaves_is_string_and_computed_fallbacks(): void
    {
        // is_string is broader-narrowing (T_String::coerce is is_scalar); a computed
        // fallback isn't a default — both advisory only, never auto-fixed.
        $src = "<?php\nnamespace App;\nclass C {\n"
            . " public function a(\$x) { return is_string(\$x) ? \$x : 'd'; }\n"
            . " public function b(\$x) { return is_string(\$x) ? \$x : 'e'; }\n"
            . " public function c(\$x) { return is_scalar(\$x) ? (string) \$x : (string) json_encode(\$x); }\n"
            . " public function d(\$x) { return is_scalar(\$x) ? (string) \$x : (string) json_encode(\$x); }\n"
            . "}\n";

        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
    }

    private function judge(string $body): \JesseGall\CodeCommandments\Results\Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\nclass Conf {\n{$body}\n private function get(\$k, \$d = null) { return \$d; }\n}\n");
    }

    public function test_flags_a_repeated_numeric_int_coercion(): void
    {
        $j = $this->judge('
            public function a(): int { $v = $this->get("a", 1); return is_numeric($v) ? (int) $v : 1; }
            public function b(): int { $v = $this->get("b", 2); return is_numeric($v) ? (int) $v : 2; }
        ');

        $this->assertCount(2, $j->warnings);
        $this->assertStringContainsString('T_Int::coerce', $j->warnings[0]->message);
    }

    public function test_finds_a_cast_wrapped_in_another_call(): void
    {
        $j = $this->judge('
            public function a(): int { $v = $this->get("a", 1); return is_numeric($v) ? (int) $v : 1; }
            public function b(): int { $v = $this->get("b", 2); return is_numeric($v) ? max(1, (int) $v) : 2; }
        ');

        $this->assertCount(2, $j->warnings, 'A cast nested in max(...) is still the same shape.');
    }

    public function test_flags_a_repeated_string_keep_coercion(): void
    {
        $j = $this->judge('
            public function a(): ?string { $v = $this->get("a"); return is_string($v) && $v !== "" ? $v : null; }
            public function b(): ?string { $v = $this->get("b"); return is_string($v) && $v !== "" ? $v : null; }
        ');

        $this->assertCount(2, $j->warnings);
    }

    public function test_does_not_flag_a_single_occurrence(): void
    {
        $j = $this->judge('
            public function a(): int { $v = $this->get("a", 1); return is_numeric($v) ? (int) $v : 1; }
            public function b(): float { $v = $this->get("b", 2.0); return is_numeric($v) ? (float) $v : 2.0; }
        ');

        // int and float are different shapes — each appears once, so neither fires.
        $this->assertFalse($j->hasWarnings());
    }

    public function test_does_not_flag_a_plain_fallback_ternary(): void
    {
        // No type guard + coercion — that is RepeatedFallback's territory.
        $j = $this->judge('
            public function a(): string { return $this->get("a") ?: "x"; }
            public function b(): string { return $this->get("b") ?: "y"; }
        ');

        $this->assertFalse($j->hasWarnings());
    }

    public function test_respects_min_occurrences_config(): void
    {
        $this->prophet->configure(['min_occurrences' => 3]);
        $j = $this->judge('
            public function a(): int { $v = $this->get("a", 1); return is_numeric($v) ? (int) $v : 1; }
            public function b(): int { $v = $this->get("b", 2); return is_numeric($v) ? (int) $v : 2; }
        ');

        $this->assertFalse($j->hasWarnings(), 'Two occurrences is below the configured threshold of 3.');
    }
}
