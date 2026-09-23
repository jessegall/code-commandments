<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hooks;

use JesseGall\CodeCommandments\Hooks\HookRegistry;
use JesseGall\PhpTypes\Option;

/**
 * The file the journal hands a plugin for commands it runs later, one per line, as the plugin — how advice
 * found after a tool call was answered still reaches the agent, as a nudge: told to it, and shown in the
 * chat as a mark rather than a message the user reads as the agent's own.
 */
final readonly class JournalQueue
{
    public const string PATH = 'JOURNAL_QUEUE';

    /**
     * The longest title the journal takes.
     */
    private const int TITLE = 80;

    private function __construct(private string $path) {}

    /**
     * The queue the journal named for this plugin — none when it named none.
     *
     * @return Option<self>
     */
    public static function fromEnvironment(): Option
    {
        $path = getenv(self::PATH) ?: '';

        return $path === '' ? Option::none() : Option::some(new self($path));
    }

    /**
     * Tell the agent what $advice says — each thing it says, a nudge of its own — and raise the event it
     * carries on the journal's bus, the same card and activity an answer given at once would raise.
     */
    public function tell(JournalAnswer $advice): void
    {
        $lines = array_map(
            static fn (array $said): string => 'nudge create ' . escapeshellarg(self::title($said[0])) . ' --brief ' . escapeshellarg(self::oneLine($said[1])) . "\n",
            $advice->said(),
        );

        if ($advice->raise !== null) {
            $lines[] = 'plugin raise ' . HookRegistry::JOURNAL_PLUGIN . ' ' . escapeshellarg($advice->raise->event) . ' ' . escapeshellarg(self::oneLine($advice->raise->brief)) . "\n";
        }

        if ($lines !== []) {
            file_put_contents($this->path, implode('', $lines), FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * $text fit to be a nudge title — its first line, no colon, at most {@see TITLE} characters.
     */
    private static function title(string $text): string
    {
        $first = trim(str_replace(':', ' —', strtok($text, "\n") ?: $text));

        return mb_strlen($first) <= self::TITLE ? $first : rtrim(mb_substr($first, 0, self::TITLE - 1)) . '…';
    }

    /**
     * $text on one line — the queue reads a command per line, so a line break becomes a separator.
     */
    private static function oneLine(string $text): string
    {
        return implode(' · ', array_filter(array_map(trim(...), explode("\n", $text)), static fn (string $line): bool => $line !== ''));
    }
}
