<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * The README's auto-generated sections (prophet + command tables) must be in
 * sync with the source classes. The pre-commit hook regenerates them; this guards
 * the case where a prophet/command is added or its description changed without
 * the hook running (so CI / `composer test` catches it).
 */
class ReadmeIsCurrentTest extends TestCase
{
    public function test_readme_autogen_sections_are_up_to_date(): void
    {
        $root = dirname(__DIR__, 2);

        exec('php ' . escapeshellarg($root . '/scripts/generate-readme.php') . ' --check 2>&1', $output, $code);

        $this->assertSame(
            0,
            $code,
            "README.md is out of date — run `composer readme`.\n" . implode("\n", $output),
        );
    }
}
