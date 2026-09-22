<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Sins\Catalog;

/**
 * `commandments journal-config` — writes the agent journal plugin's chosen switches into the
 * project's `.commandments/config.php`: a sin switched off is disabled, one switched on enabled, and
 * the same for each language. The journal hands every chosen setting as JSON in JOURNAL_SETTINGS.
 */
final class JournalConfig implements Command
{
    public const SETTINGS = 'JOURNAL_SETTINGS';

    public const PROJECT = 'CLAUDE_PROJECT_DIR';

    public function names(): array
    {
        return ['journal-config'];
    }

    public function help(): Help
    {
        return Help::of("Write the agent journal plugin's chosen switches into .commandments/config.php.")
            ->form('journal-config', 'apply JOURNAL_SETTINGS to the project config (run by the journal plugin)');
    }

    public function run(Input $input): int
    {
        $chosen = json_decode((string) getenv(self::SETTINGS), true);

        if (! is_array($chosen)) {
            fwrite(STDERR, self::SETTINGS . " holds no settings to apply.\n");

            return 2;
        }

        $file = ConfigFile::inProject(getenv(self::PROJECT) ?: null);
        $changed = 0;

        foreach (Language::cases() as $language) {
            $on = $chosen[JournalManifest::languageKey($language)] ?? null;

            if ($on !== null) {
                $changed += (int) ($on === 'false' ? $file->disableLanguage($language) : $file->enableLanguage($language));
            }
        }

        foreach (Catalog::every() as $sin) {
            $on = $chosen[JournalManifest::sinKey($sin)] ?? null;

            if ($on !== null) {
                $changed += (int) ($on === 'false' ? $file->disable($sin::class) : $file->enable($sin::class));
            }
        }

        echo json_encode($changed ? ['notify' => "config.php follows the plugin's switches: {$changed} changed"] : new \stdClass) . "\n";

        return 0;
    }
}
