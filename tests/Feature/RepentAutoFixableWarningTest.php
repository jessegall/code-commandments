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

        $this->artisan('commandments:repent', ['--scroll' => 'warnscroll'])
            ->assertSuccessful();

        $this->assertStringContainsString('AUTOFIXED', file_get_contents($file));
        $this->assertStringNotContainsString('AUTOFIX_ME', file_get_contents($file));
    }
}
