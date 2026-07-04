<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Merges what several handlers emit for ONE hook moment into a single Claude Code response — the
 * aggregation the harness used to do across separate hook processes, now that {@see HookDispatch} runs
 * the whole registry in one invocation. Pure: given the raw emissions, it returns the one payload to
 * write, or null to stay silent (the graceful no-op — Claude Code just continues).
 *
 * The rules mirror what running the hooks separately produced: a BLOCK wins (a `Stop` that any handler
 * blocks stays blocked, with the reasons joined into one message); otherwise the non-blocking context
 * every handler injected is concatenated into one, kept out of the transcript only when they all asked.
 */
final class HookResponse
{
    /**
     * @param  list<array<string, mixed>>  $emitted  each handler's emitted payload, in order
     * @return array<string, mixed>|null  the single response to emit, or null to stay silent
     */
    public static function merge(array $emitted, string $event): ?array
    {
        $reasons = [];

        foreach ($emitted as $payload) {
            if (($payload['decision'] ?? null) === 'block' && ($reason = trim((string) ($payload['reason'] ?? ''))) !== '') {
                $reasons[] = $reason;
            }
        }

        if ($reasons !== []) {
            return ['decision' => 'block', 'reason' => implode("\n\n", $reasons)];
        }

        $contexts = [];
        $suppress = true;

        foreach ($emitted as $payload) {
            $context = $payload['hookSpecificOutput']['additionalContext'] ?? null;

            if (is_string($context) && $context !== '') {
                $contexts[] = $context;
                $suppress = $suppress && (($payload['suppressOutput'] ?? false) === true);
            }
        }

        if ($contexts === []) {
            return null;
        }

        $response = ['hookSpecificOutput' => ['hookEventName' => $event, 'additionalContext' => implode("\n\n", $contexts)]];

        if ($suppress) {
            $response['suppressOutput'] = true;
        }

        return $response;
    }
}
