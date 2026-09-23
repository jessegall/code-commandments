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
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

/**
 * The agent journal plugin's switches land in the project's config.php — in the project the
 * journal names, never in the plugin's own folder.
 */
final class JournalConfigTest extends TestCase
{
    use TemporaryFolder;

    protected function setUp(): void
    {
        putenv(JournalConfig::PROJECT . '=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv(JournalConfig::PROJECT);
        putenv(JournalConfig::SETTINGS);
    }

    /**
     * @param  array<string, string>  $chosen
     */
    private function apply(array $chosen): ConfigFile
    {
        $this->answered($chosen);

        return ConfigFile::inProject($this->root);
    }

    /**
     * @param  array<string, string>  $chosen
     * @return array<string, mixed>
     */
    private function answered(array $chosen): array
    {
        putenv(JournalConfig::SETTINGS . '=' . json_encode($chosen));
        ob_start();
        new JournalConfig()->run(Input::of('journal-config'));

        return (array) json_decode((string) ob_get_clean(), true);
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

    public function test_the_folder_lists_reach_config_php(): void
    {
        $file = $this->apply([JournalManifest::JUDGED => "src\napp", JournalManifest::SKIPPED => 'src/Generated']);

        $this->assertSame(['src', 'app'], $file->paths());
        $this->assertStringContainsString("exclude('src/Generated')", (string) file_get_contents($file->path));

        $file = $this->apply([JournalManifest::JUDGED => '', JournalManifest::SKIPPED => '']);

        $this->assertSame(['src', 'app'], $file->paths(), 'no folders to check keeps the ones config.php names');
        $this->assertStringNotContainsString('src/Generated', (string) file_get_contents($file->path), 'an empty leave-out list leaves nothing out');
    }

    public function test_every_sin_and_language_has_a_switch(): void
    {
        $settings = JournalManifest::settings();

        $this->assertCount(count(Language::cases()) + count(Detectors::all()) + 2, $settings, "a switch per language and per sin, and the two folder lists");
        $this->assertSame("list", $settings[JournalManifest::JUDGED]["type"]);
        $this->assertSame('flag', $settings[JournalManifest::languageKey(Language::Php)]['type']);
    }
}
