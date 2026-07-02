<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Wires code-commandments' Claude Code hooks into the project's `.claude/settings.json` — shared by
 * {@see Install} (first setup) and {@see Sync} (every `composer update`), so the hooks self-heal to
 * the current wiring instead of freezing at install time. Two hooks, one mechanism:
 *
 *  - the cardinal-rule heartbeat ({@see Remind}) under `PostToolUse`, and
 *  - the "did you judge?" nudge ({@see JudgeReminder}) under `Stop`.
 *
 * Idempotent, and MIGRATING: it removes ONLY our own hooks — any command mentioning `commandments`
 * (never the composer-script `sync`, which isn't a settings hook) — from every event, then adds back
 * exactly the current set under their current events. So an older hook wired under the wrong event
 * (a `remind` under `UserPromptSubmit`) moves; every hook the project itself wrote, in any event, is
 * preserved untouched. Idempotent by CONTENT: it writes only when the settings actually change.
 */
final class Hooks
{
    /**
     * Our hooks as (event → command). Anchored at $CLAUDE_PROJECT_DIR (the absolute project root the
     * harness gives every hook) — a relative `vendor/bin/...` silently dies when Claude's working
     * directory isn't the project root. Add a hook by adding a line here; the wiring is generic.
     */
    private const array HOOKS = [
        'PostToolUse' => 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments" remind',
        'Stop' => 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments" judge-reminder',
    ];

    public static function wire(string $path): bool
    {
        /** @var array<string, mixed> $settings */
        $settings = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        $before = json_encode($settings);

        $hooks = self::stripOurs(is_array($settings['hooks'] ?? null) ? $settings['hooks'] : []);

        foreach (self::HOOKS as $event => $command) {
            $hooks[$event][] = ['hooks' => [['type' => 'command', 'command' => $command]]];
        }

        $settings['hooks'] = $hooks;

        if (json_encode($settings) === $before) {
            return false;
        }

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return true;
    }

    /**
     * Drop every one of OUR hooks from every event — each other hook, in any event, is preserved.
     * An event left empty is removed so it doesn't linger as an empty array.
     *
     * @param  array<string, mixed>  $hooks
     * @return array<string, mixed>
     */
    private static function stripOurs(array $hooks): array
    {
        foreach ($hooks as $event => $groups) {
            $rebuilt = [];

            foreach (is_array($groups) ? $groups : [] as $group) {
                if (! is_array($group) || ! is_array($group['hooks'] ?? null)) {
                    $rebuilt[] = $group;

                    continue;
                }

                $group['hooks'] = array_values(array_filter(
                    $group['hooks'],
                    static fn ($hook): bool => ! self::isOurs(is_array($hook) ? (string) ($hook['command'] ?? '') : ''),
                ));

                if ($group['hooks'] !== []) {
                    $rebuilt[] = $group;
                }
            }

            if ($rebuilt === []) {
                unset($hooks[$event]);
            } else {
                $hooks[$event] = array_values($rebuilt);
            }
        }

        return $hooks;
    }

    /**
     * Is $command one of OURS — a `commandments` invocation? Matches every form our hooks have taken
     * (quoted, anchored at `$CLAUDE_PROJECT_DIR`, any subcommand), so a stale wiring is found and
     * replaced. The composer-script `sync` never appears in settings, so it is never in play here.
     */
    private static function isOurs(string $command): bool
    {
        return str_contains($command, 'commandments');
    }
}
