<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Hooks\JournalConfig;
use JesseGall\CodeCommandments\Cli\Hooks\JournalManifest;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Sins\Catalog;
use PHPUnit\Framework\TestCase;

/**
 * The agent journal plugin's switches land in the project's config.php — in the project the
 * journal names, never in the plugin's own folder.
 */
final class JournalConfigTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-journal-config-' . uniqid('', true);
        mkdir($this->root, 0777, true);
        putenv(JournalConfig::PROJECT . '=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv(JournalConfig::PROJECT);
        putenv(JournalConfig::SETTINGS);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @param  array<string, string>  $chosen
     */
    private function apply(array $chosen): ConfigFile
    {
        putenv(JournalConfig::SETTINGS . '=' . json_encode($chosen));
        ob_start();
        new JournalConfig()->run(Input::of('journal-config'));
        ob_end_clean();

        return ConfigFile::inProject($this->root);
    }

    public function test_a_switch_turned_off_disables_its_rule_and_its_language(): void
    {
        $sin = Catalog::every()[0];
        $file = $this->apply([JournalManifest::sinKey($sin) => 'false', JournalManifest::languageKey(Language::TypeScript) => 'false']);

        $this->assertContains($sin::class, $file->disabled());
        $this->assertSame([Language::TypeScript], $file->disabledLanguages());

        $file = $this->apply([JournalManifest::sinKey($sin) => 'true', JournalManifest::languageKey(Language::TypeScript) => 'true']);

        $this->assertNotContains($sin::class, $file->disabled(), 'switched back on, it is enabled again');
        $this->assertSame([], $file->disabledLanguages());
    }

    public function test_every_sin_and_language_has_a_switch(): void
    {
        $settings = JournalManifest::settings();

        $this->assertCount(count(Language::cases()) + count(Detectors::all()), $settings);
        $this->assertSame('flag', $settings[JournalManifest::languageKey(Language::Php)]['type']);
    }
}
