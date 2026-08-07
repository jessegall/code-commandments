<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * Merges what several handlers emit for one hook moment into a single Claude Code response, or null to
 * stay silent. A BLOCK wins — the blocking handlers' reasons are joined into one message; otherwise the
 * non-blocking context each handler injected is concatenated, suppressed from the transcript only when
 * they all asked.
 */
final class HookResponse
{
    /**
     * @param  list<array<string, mixed>>  $emitted  each handler's emitted payload, in order
     * @return array<string, mixed>|null  the single response to emit, or null to stay silent
     */
    public static function merge(array $emitted, string $event): ?array
    {
        $emissions = array_map(HookEmission::of(...), $emitted);
        $reasons = [];

        foreach ($emissions as $emission) {
            foreach ($emission->blockReason() as $reason) {
                $reasons[] = $reason;
            }
        }

        if ($reasons !== []) {
            return ['decision' => 'block', 'reason' => implode("\n\n", $reasons)];
        }

        $contexts = [];
        $suppress = true;

        foreach ($emissions as $emission) {
            foreach ($emission->context() as $context) {
                $contexts[] = $context;
                $suppress = $suppress && $emission->suppressesOutput();
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
