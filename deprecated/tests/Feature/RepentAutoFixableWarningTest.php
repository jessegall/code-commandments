<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Tests\Fixtures\Prophets\AutoFixWarningProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * #48: repent must act on [AUTO-FIXABLE] WARNINGS, not just sins — no severity
 * bump required.
 */
class RepentAutoFixableWarningTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/cc-repent-warn-' . uniqid();
        @mkdir($this->dir, 0755, true);
        Environment::setBasePath($this->dir);

        $registry = app(ProphetRegistry::class);
        $registry->registerMany('warnscroll', [AutoFixWarningProphet::class]);
        $registry->setScrollConfig('warnscroll', [
            'path' => $this->dir,
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => [AutoFixWarningProphet::class],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_repent_fixes_an_autofixable_warning_without_a_severity_bump(): void
    {
        $file = $this->dir . '/Target.php';
        file_put_contents($file, "<?php\n// AUTOFIX_ME\n");

        // --all: a repo-wide sweep of the (isolated, non-git) fixture scroll.
        // Bare `repent` now defaults to git scope (#196), which a temp dir lacks.
        $this->artisan('commandments:repent', ['--scroll' => 'warnscroll', '--all' => true])
            ->assertSuccessful();

        $this->assertStringContainsString('AUTOFIXED', file_get_contents($file));
        $this->assertStringNotContainsString('AUTOFIX_ME', file_get_contents($file));
    }

    public function test_bare_repent_does_not_sweep_the_whole_scroll(): void
    {
        // #196: a bare `repent` (no scope flag) must default to git working-tree
        // scope and NEVER sweep the scroll repo-wide. The fixture below is an
        // auto-fixable file that is NOT in any git changeset (the base path is a
        // bare temp dir), so a bare run must leave it untouched.
        $file = $this->dir . '/Untracked.php';
        file_put_contents($file, "<?php\n// AUTOFIX_ME\n");

        $this->artisan('commandments:repent', ['--scroll' => 'warnscroll'])
            ->expectsOutputToContain('righteous')
            ->assertSuccessful();

        // Untouched — the default scope did not reach this unrelated file.
        $this->assertStringContainsString('AUTOFIX_ME', file_get_contents($file));
        $this->assertStringNotContainsString('AUTOFIXED', file_get_contents($file));
    }
}
