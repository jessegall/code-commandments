<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Skills\Library;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments journal-skills` — renders the skills into the plugin's `journal-skills/` folder, for the languages
 * the project writes; the journal publishes them into the project and takes them back on removal.
 */
final class JournalSkills implements Command
{
    public const OUT = 'journal-skills';

    public function names(): array
    {
        return ['journal-skills'];
    }

    public function help(): Help
    {
        return Help::of("Render the skills into the agent journal plugin's folder, for the journal to publish.")
            ->form('journal-skills', 'render the skills into ./' . self::OUT . '/.agents/skills (run by the journal plugin)');
    }

    public function run(Input $input): int
    {
        echo json_encode(new \stdClass) . "\n";
        fwrite(STDERR, self::render() . " skills rendered for the journal to publish\n");

        return 0;
    }

    public static function render(): int
    {
        $plugin = dirname(__DIR__, 3);
        $project = getenv(JournalConfig::PROJECT) ?: null;

        return count(new Library(Workspace::at($plugin . '/' . self::OUT), Languages::from(Config::load($project)))->publish($plugin));
    }
}
