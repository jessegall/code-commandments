<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Config\ConfigScribe;
use JesseGall\CodeCommandments\Cli\Config\DisableMenu;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;
use PHPUnit\Framework\TestCase;

/**
 * {@see DisableMenu} keeps the `$disabledSkills` / `$disabledSins` menus in the consumer's
 * config: every shipped skill and sin as a commented-out `disable()` argument, appended when
 * missing, the human's lines never rewritten, the file always valid PHP.
 */
final class DisableMenuTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-menu-' . uniqid('', true);
        mkdir($this->dir . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_ensure_writes_both_menus_into_a_fresh_scaffold(): void
    {
        $this->scaffold();

        DisableMenu::inProject($this->dir)->ensure();

        $config = $this->config();
        $this->assertStringContainsString('$disabledSkills = function (Config $config): void {', $config);
        $this->assertStringContainsString('$disabledSins = function (Config $config): void {', $config);
        $this->assertStringContainsString('// Skills\Backend\ValueObjects::class,', $config);
        $this->assertStringContainsString('// Sins\Backend\FeatureEnvy::class,', $config);
        $this->assertStringContainsString('// Sins\Frontend\SwitchCase::class,', $config);
        $this->assertStringContainsString('$disabledHooks = function (Config $config): void {', $config);
        $this->assertStringContainsString('// Hooks\Handlers\JudgeReminder::class,', $config);
        $this->assertStringContainsString('use ($disabledSkills, $disabledSins, $disabledHooks)', $config);
        $this->assertStringContainsString('$disabledSkills($config);', $config);
        $this->assertStringContainsString('$disabledSins($config);', $config);
        $this->assertStringContainsString('$disabledHooks($config);', $config);
        $this->assertValidPhp();
    }

    public function test_uncommenting_a_hook_disables_it(): void
    {
        $this->scaffold();
        DisableMenu::inProject($this->dir)->ensure();

        $this->write(str_replace(
            '// Hooks\Handlers\JudgeReminder::class,',
            'Hooks\Handlers\JudgeReminder::class,',
            $this->config(),
        ));
        DisableMenu::inProject($this->dir)->ensure(); // survives a re-ensure

        $this->assertContains(\JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder::class, $this->disabled());
        $this->assertSame(1, substr_count($this->config(), 'Hooks\Handlers\JudgeReminder::class'), 'not duplicated');
        $this->assertValidPhp();
    }

    public function test_ensure_is_idempotent(): void
    {
        $this->scaffold();

        DisableMenu::inProject($this->dir)->ensure();
        $once = $this->config();

        DisableMenu::inProject($this->dir)->ensure();
        $this->assertSame($once, $this->config());
    }

    public function test_an_uncommented_line_disables_and_survives_a_re_ensure(): void
    {
        $this->scaffold();
        DisableMenu::inProject($this->dir)->ensure();

        $this->write(str_replace('// Skills\Backend\ValueObjects::class,', 'Skills\Backend\ValueObjects::class,', $this->config()));
        DisableMenu::inProject($this->dir)->ensure();

        $this->assertStringContainsString("\n        Skills\Backend\ValueObjects::class,", $this->config(), 'still uncommented');
        $this->assertSame(1, substr_count($this->config(), 'Skills\Backend\ValueObjects::class'), 'not duplicated');
        $this->assertContains(ValueObjects::class, $this->disabled());
        $this->assertValidPhp();
    }

    public function test_a_deleted_entry_is_re_appended_commented(): void
    {
        $this->scaffold();
        DisableMenu::inProject($this->dir)->ensure();

        $this->write(str_replace("        // Sins\Backend\FeatureEnvy::class,\n", '', $this->config()));
        DisableMenu::inProject($this->dir)->ensure();

        $this->assertStringContainsString('// Sins\Backend\FeatureEnvy::class,', $this->config());
        $this->assertNotContains(FeatureEnvy::class, $this->disabled());
        $this->assertValidPhp();
    }

    public function test_ensure_regroups_a_backend_entry_that_drifted_into_frontend(): void
    {
        $this->scaffold();
        DisableMenu::inProject($this->dir)->ensure();

        // Simulate the old append bug: a backend skill sitting below the Frontend divider.
        $line = '        // Skills\Backend\ValueObjects::class,';
        $config = str_replace($line . "\n", '', $this->config());
        $sep = '        ' . '// ----------[ Frontend ]----------';
        $at = strpos($config, $sep);
        $config = substr($config, 0, $at) . $line . "\n" . substr($config, $at);
        $this->write($config);

        DisableMenu::inProject($this->dir)->ensure();

        $healed = $this->config();
        $backendEntry = strpos($healed, 'Skills\Backend\ValueObjects::class');
        $frontendDivider = strpos($healed, '// ----------[ Frontend ]----------');

        $this->assertNotFalse($backendEntry);
        $this->assertLessThan($frontendDivider, $backendEntry, 'the backend skill was regrouped above the Frontend divider');
        $this->assertSame(1, substr_count($healed, 'Skills\Backend\ValueObjects::class'), 'not duplicated');
        $this->assertStringContainsString("\n\n        // ----------[ Frontend ]----------", $healed, 'a blank line precedes the Frontend section');
        $this->assertValidPhp();
    }

    public function test_regroup_preserves_an_uncommented_entry_and_moves_it_to_its_group(): void
    {
        $this->scaffold();
        DisableMenu::inProject($this->dir)->ensure();

        // An active (uncommented) backend sin, dropped below the Frontend divider.
        $line = 'Sins\Backend\FeatureEnvy::class,';
        $config = str_replace('        // ' . $line . "\n", '', $this->config());
        $sep = '        ' . '// ----------[ Frontend ]----------';
        // Second divider = the sins menu's (FeatureEnvy is a sin).
        $at = strpos($config, $sep, strpos($config, $sep) + 1);
        $config = substr($config, 0, $at) . '        ' . $line . "\n" . substr($config, $at);
        $this->write($config);

        DisableMenu::inProject($this->dir)->ensure();

        $healed = $this->config();
        $this->assertContains(FeatureEnvy::class, $this->disabled(), 'still disabled — the uncommented entry survived');
        $this->assertSame(1, substr_count($healed, 'Sins\Backend\FeatureEnvy::class'), 'not duplicated');
        // It sits above the sins menu's Frontend divider, still uncommented.
        $entry = strpos($healed, "\n        Sins\Backend\FeatureEnvy::class,");
        $this->assertNotFalse($entry, 'regrouped and still uncommented');
        $this->assertValidPhp();
    }

    public function test_ensure_upgrades_a_config_without_menus_and_keeps_its_lines(): void
    {
        $this->write(<<<'PHP'
            <?php

            declare(strict_types=1);

            use JesseGall\CodeCommandments\Config;

            return function (Config $config): void {
                $config->paths('app');

                $config->disable(
                    \JesseGall\CodeCommandments\Sins\Backend\SwallowCatch::class,
                );
            };

            PHP);

        DisableMenu::inProject($this->dir)->ensure();

        $config = $this->config();
        $this->assertStringContainsString('\JesseGall\CodeCommandments\Sins\Backend\SwallowCatch::class,', $config, 'human line kept');
        $this->assertStringContainsString('use ($disabledSkills, $disabledSins, $disabledHooks)', $config);
        $this->assertStringContainsString('// Skills\Backend\ValueObjects::class,', $config);
        $this->assertValidPhp();
    }

    public function test_an_unrecognizable_config_is_left_untouched(): void
    {
        $this->write("<?php\n\nreturn new \JesseGall\CodeCommandments\Config;\n");

        DisableMenu::inProject($this->dir)->ensure();

        $this->assertSame("<?php\n\nreturn new \JesseGall\CodeCommandments\Config;\n", $this->config());
    }

    // ----------[ Helpers ]----------

    private function scaffold(): void
    {
        new ConfigScribe($this->path())->scaffold(['app', 'src']);
    }

    /**
     * @return list<class-string>
     */
    private function disabled(): array
    {
        $config = Config::load($this->dir);
        $property = new \ReflectionProperty($config, 'disabled');

        return $property->getValue($config);
    }

    private function config(): string
    {
        return (string) file_get_contents($this->path());
    }

    private function write(string $source): void
    {
        file_put_contents($this->path(), $source);
    }

    private function path(): string
    {
        return $this->dir . '/.commandments/config.php';
    }

    private function assertValidPhp(): void
    {
        exec('php -l ' . escapeshellarg($this->path()) . ' 2>&1', $output, $exit);

        $this->assertSame(0, $exit, implode("\n", $output));
    }
}
