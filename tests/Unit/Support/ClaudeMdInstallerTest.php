<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ClaudeHooksInstaller;
use JesseGall\CodeCommandments\Support\ClaudeMdInstaller;
use PHPUnit\Framework\TestCase;

class ClaudeMdInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-md-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function path(): string
    {
        return $this->dir . '/CLAUDE.md';
    }

    // --- the splice must never treat the block as a regex replacement ---

    public function test_replaceInto_inserts_block_literally_even_with_regex_metachars(): void
    {
        $content = "# Title\n\n" . ClaudeMdInstaller::BEGIN . "\nold\n" . ClaudeMdInstaller::END . "\n\n## Keep me\nmine\n";
        $block = ClaudeMdInstaller::BEGIN . "\n## Code Commandments\nliteral \$1 and \${u} and App\\Support\n" . ClaudeMdInstaller::END;

        $out = ClaudeMdInstaller::replaceInto($content, $block);

        $this->assertNotNull($out);
        $this->assertStringContainsString('literal $1 and ${u} and App\\Support', $out);
        // Consumer content after the fence is preserved.
        $this->assertStringContainsString("## Keep me\nmine", $out);
        $this->assertStringNotContainsString('old', $out);
    }

    public function test_replaceInto_returns_null_when_no_section(): void
    {
        $this->assertNull(ClaudeMdInstaller::replaceInto("# Just a readme\n\nno section here\n", 'BLOCK'));
    }

    public function test_replaceInto_upgrades_a_legacy_heading_to_sentinels(): void
    {
        // Old install wrote a bare heading with no sentinels.
        $content = "# App\n\n## Code Commandments\nOLD body text\n\n## Other\nkeep\n";
        $block = ClaudeMdInstaller::section(ClaudeHooksInstaller::STANDALONE);

        $out = ClaudeMdInstaller::replaceInto($content, $block);

        $this->assertNotNull($out);
        $this->assertStringContainsString(ClaudeMdInstaller::BEGIN, $out);
        $this->assertStringContainsString(ClaudeMdInstaller::END, $out);
        $this->assertStringNotContainsString('OLD body text', $out);
        $this->assertStringContainsString("## Other\nkeep", $out);
    }

    // --- reassert: replace-only, idempotent, conflict-safe ---

    public function test_reassert_is_no_section_when_file_absent(): void
    {
        $this->assertSame(ClaudeMdInstaller::STATUS_NO_SECTION, ClaudeMdInstaller::reassert($this->dir));
    }

    public function test_reassert_is_no_section_when_section_absent(): void
    {
        file_put_contents($this->path(), "# Readme\n\nno commandments here\n");
        $this->assertSame(ClaudeMdInstaller::STATUS_NO_SECTION, ClaudeMdInstaller::reassert($this->dir));
        // Untouched.
        $this->assertStringNotContainsString('Code Commandments', (string) file_get_contents($this->path()));
    }

    public function test_reassert_replaces_a_stale_section(): void
    {
        file_put_contents($this->path(), "# App\n\n## Code Commandments\nSTALE old wording\n");
        $this->assertSame(ClaudeMdInstaller::STATUS_REPLACED, ClaudeMdInstaller::reassert($this->dir));
        $md = (string) file_get_contents($this->path());
        $this->assertStringNotContainsString('STALE old wording', $md);
        $this->assertStringContainsString('The guided workflow', $md);
    }

    public function test_reassert_is_idempotent(): void
    {
        ClaudeMdInstaller::install($this->dir);
        $this->assertSame(ClaudeMdInstaller::STATUS_UNCHANGED, ClaudeMdInstaller::reassert($this->dir));
    }

    public function test_reassert_skips_a_file_with_conflict_markers(): void
    {
        file_put_contents($this->path(), "## Code Commandments\nx\n<<<<<<< HEAD\na\n=======\nb\n>>>>>>> other\n");
        $this->assertSame(ClaudeMdInstaller::STATUS_SKIPPED_CONFLICT, ClaudeMdInstaller::reassert($this->dir));
    }

    // --- install: create / append / replace ---

    public function test_install_creates_when_missing(): void
    {
        $this->assertSame(ClaudeMdInstaller::STATUS_CREATED, ClaudeMdInstaller::install($this->dir));
        $this->assertStringContainsString('## Code Commandments', (string) file_get_contents($this->path()));
    }

    public function test_install_appends_preserving_existing_content(): void
    {
        file_put_contents($this->path(), "# My project\n\nimportant notes\n");
        $this->assertSame(ClaudeMdInstaller::STATUS_APPENDED, ClaudeMdInstaller::install($this->dir));
        $md = (string) file_get_contents($this->path());
        $this->assertStringContainsString('important notes', $md);
        $this->assertStringContainsString(ClaudeMdInstaller::BEGIN, $md);
    }

    public function test_artisan_and_standalone_bodies_use_their_runner(): void
    {
        $artisan = ClaudeMdInstaller::section(ClaudeHooksInstaller::ARTISAN);
        $standalone = ClaudeMdInstaller::section(ClaudeHooksInstaller::STANDALONE);

        $this->assertStringContainsString('php artisan commandments:judge --next --git', $artisan);
        $this->assertStringContainsString('vendor/bin/commandments judge --next --git', $standalone);
    }

    public function test_settings_instructions_are_identical_modulo_runner(): void
    {
        $laravel = $this->dir . '/laravel';
        mkdir($laravel, 0755, true);
        touch($laravel . '/artisan');
        $artisan = ClaudeMdInstaller::settingsInstructions($laravel);
        $standalone = ClaudeMdInstaller::settingsInstructions($this->dir);

        // Both carry the fuller REPORT-IS-NOT-A-DODGE wording (audit #16) and the
        // same absolve-reason string (audit #19).
        foreach ([$artisan, $standalone] as $text) {
            $this->assertStringContainsString('"I disagree" is not', $text);
            $this->assertStringContainsString('accept pre-existing backlog', $text);
            $this->assertStringContainsString('READ EACH OUTPUT IN FULL', $text);
            $this->assertStringNotContainsString('judge --next', $text);
        }
        $this->assertStringContainsString('php artisan commandments:pilgrimage', $artisan);
        $this->assertStringContainsString('vendor/bin/commandments pilgrimage', $standalone);
    }
}
