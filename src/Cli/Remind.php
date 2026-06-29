<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments remind` — emits the cardinal rule as a `UserPromptSubmit` hook
 * payload, so it is re-injected into context on EVERY turn (not just at session
 * start, where a long session would let it fade). Wired by {@see Install}.
 *
 * The text is a tight distillation of `fix-at-the-source` — it fires constantly,
 * so it stays short to keep the per-turn token cost negligible.
 */
final class Remind
{
    private const string REMINDER =
        'Code Commandments — THE MOST IMPORTANT RULE: trace every fix to its SOURCE. '
        . 'A finding is a symptom; fix where the bad value/type/shape is BORN, never where '
        . 'it surfaces. Do NOT silence a detector with a ?? default, cast, null-check, wrapper, '
        . 'constructor override, or try/catch — that launders the problem. If the honest fix '
        . 'touches many call sites, touch them; that breadth is the bug surfacing.';

    public function run(array $args): int
    {
        $payload = [
            'suppressOutput' => true,
            'hookSpecificOutput' => [
                'hookEventName' => 'UserPromptSubmit',
                'additionalContext' => self::REMINDER,
            ],
        ];

        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return 0;
    }
}
