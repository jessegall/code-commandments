<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Config\SourceRoots;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;

/**
 * `commandments journal-scan` — run by the journal right after install: finds the project's source
 * folders and the generated ones to leave out, and answers them as the plugin's settings.
 */
final class JournalScan implements Command
{
    public function names(): array
    {
        return ['journal-scan'];
    }

    public function help(): Help
    {
        return Help::of('Scan the project for the folders to check and the ones to leave out, for the agent journal plugin.')
            ->form('journal-scan', 'answer the detected folders as settings (run by the journal plugin)');
    }

    public function run(Input $input): int
    {
        $project = (string) (getenv(JournalConfig::PROJECT) ?: getcwd());
        $roots = new SourceRoots();

        echo json_encode(['settings' => [
            JournalManifest::JUDGED => implode("\n", $roots->detect($project)),
            JournalManifest::SKIPPED => implode("\n", $roots->built($project)),
        ]], JSON_UNESCAPED_SLASHES) . "\n";

        return 0;
    }
}
