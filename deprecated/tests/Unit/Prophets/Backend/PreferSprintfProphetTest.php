<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferSprintfProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferSprintfProphetTest extends TestCase
{
    private PreferSprintfProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferSprintfProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Detection
    // ────────────────────────────────────────────────────────────────

    public function test_flags_interpolation_with_escape_sequence(): void
    {
        $judgment = $this->judgeBody('return "Problems:\n\n{$bulleted}";');

        $this->assertFallen($judgment, 1);
        $this->assertStringContainsString('sprintf(', $judgment->sins[0]->suggestion);
    }

    public function test_does_not_flag_plain_interpolation_without_escape(): void
    {
        $this->assertTrue($this->judgeBody('return "Hello {$name}";')->isRighteous());
        $this->assertTrue($this->judgeBody('return "Hi $name";')->isRighteous());
    }

    public function test_does_not_flag_a_string_without_interpolation(): void
    {
        $this->assertTrue($this->judgeBody('return "just text";')->isRighteous());
    }

    public function test_does_not_flag_heredoc(): void
    {
        $body = <<<'PHP'
        return <<<TXT
        Problems:

        {$bulleted}
        TXT;
        PHP;

        $this->assertTrue($this->judgeBody($body)->isRighteous());
    }

    public function test_can_widen_to_all_interpolations(): void
    {
        $this->prophet->configure(['require_escape' => false]);

        $this->assertFallen($this->judgeBody('return "Hello {$name}";'), 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Auto-fix (repent)
    // ────────────────────────────────────────────────────────────────

    public function test_repent_rewrites_to_sprintf_with_paragraph_constant(): void
    {
        $content = $this->wrap('public function f(string $bulleted): string { return "Problems:\n\n{$bulleted}"; }');

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString("sprintf(", $result->newContent);
        $this->assertStringContainsString("'Problems:%s%s'", $result->newContent);
        $this->assertStringContainsString('T_String::PARAGRAPH,', $result->newContent);
        $this->assertStringContainsString('$bulleted,', $result->newContent);
        $this->assertStringContainsString('use JesseGall\PhpTypes\T_String;', $result->newContent);

        // The rewrite must still parse.
        $ast = (new \PhpParser\ParserFactory)->createForNewestSupportedVersion()->parse($result->newContent);
        $this->assertNotNull($ast);
    }

    public function test_repent_escapes_literal_percent(): void
    {
        $content = $this->wrap('public function f(int $pct): string { return "100% done:\t{$pct}"; }');

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString("'100%% done:%s%s'", $result->newContent);
        $this->assertStringContainsString('T_String::TAB,', $result->newContent);
    }

    public function test_repent_handles_multiple_interpolations(): void
    {
        $content = $this->wrap('public function f(string $a, string $b): string { return "$a\n$b"; }');

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('T_String::NEWLINE,', $result->newContent);
        // Both vars appear as args.
        $this->assertStringContainsString('$a,', $result->newContent);
        $this->assertStringContainsString('$b,', $result->newContent);
    }

    public function test_is_auto_fixable(): void
    {
        $this->assertInstanceOf(
            \JesseGall\CodeCommandments\Contracts\SinRepenter::class,
            $this->prophet,
        );
    }

    // ────────────────────────────────────────────────────────────────
    // Descriptions
    // ────────────────────────────────────────────────────────────────

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertStringContainsString('sprintf', $this->prophet->description());
        $this->assertStringContainsString('PARAGRAPH', $this->prophet->detailedDescription());
        $this->assertStringContainsString('require_escape', $this->prophet->detailedDescription());
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
            'Expected fallen. Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins)),
        );

        if ($expectedSins !== null) {
            $this->assertCount($expectedSins, $judgment->sins);
        }
    }
}
