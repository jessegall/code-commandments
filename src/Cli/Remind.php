<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments remind` — the cardinal-rule reminder, fired as a `PostToolUse` hook. Rather than
 * re-inject on every turn (noisy) or once at session start (fades), it counts tool uses in a
 * marker file and surfaces the rule once every {@see INTERVAL}, then resets — a steady heartbeat
 * that keeps "trace to the source" present through a long session at a fraction of the token cost.
 * Wired by {@see Install}.
 *
 * The text is a tight distillation of `fix-at-the-source`; it stays short because it recurs.
 */
final class Remind
{
    private const int INTERVAL = 25;

    private const string REMINDER =
        'Code Commandments — THE MOST IMPORTANT RULE: trace every fix to its SOURCE. '
        . 'A finding is a symptom; fix where the bad value/type/shape is BORN, never where '
        . 'it surfaces. Do NOT silence a detector with a ?? default, cast, null-check, wrapper, '
        . 'constructor override, or try/catch — that launders the problem. If the honest fix '
        . 'touches many call sites, touch them; that breadth is the bug surfacing.';

    public function run(array $args): int
    {
        // Fire only when the tool-use count rolls over the interval; otherwise stay silent so the
        // hook adds nothing to context on the other 24 tool uses.
        if ($this->bump() < self::INTERVAL) {
            return 0;
        }

        $this->reset();

        $payload = [
            'suppressOutput' => true,
            'hookSpecificOutput' => [
                'hookEventName' => 'PostToolUse',
                'additionalContext' => self::REMINDER,
            ],
        ];

        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return 0;
    }

    /**
     * Increment the persisted tool-use count and return the new value.
     */
    private function bump(): int
    {
        $file = self::counterFile();
        $count = 1 + (is_file($file) ? (int) file_get_contents($file) : 0);

        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, (string) $count);

        return $count;
    }

    private function reset(): void
    {
        @file_put_contents(self::counterFile(), '0');
    }

    private static function counterFile(): string
    {
        $root = getenv('CLAUDE_PROJECT_DIR') ?: getcwd();

        return $root . '/.commandments/.remind-count';
    }
}
