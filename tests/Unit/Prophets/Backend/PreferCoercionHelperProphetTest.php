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
        $this->assertStringContainsString('intOr', $j->warnings[0]->message);
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
