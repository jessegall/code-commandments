<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Wires the cardinal-rule reminder as a `PostToolUse` hook in the project's `.claude/settings.json`
 * — shared by {@see Install} (first setup) and {@see Sync} (every `composer update`), so the hook
 * self-heals to the current wiring instead of freezing at install time. Idempotent, and MIGRATING:
 * it removes ONLY our own hook — matched by its command containing `commandments remind` — from
 * every event (so an older one wired under `UserPromptSubmit` moves to `PostToolUse`), then adds the
 * single current group. Every other hook the project has, in any event, is preserved untouched — we
 * never strip a hook we didn't write.
 */
final class ReminderHook
{
    // Anchored at $CLAUDE_PROJECT_DIR (the absolute project root the harness gives every hook) — a
    // relative `vendor/bin/...` silently dies when Claude's working directory isn't the project root.
    public const string COMMAND = 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments" remind';

    /**
     * Ensure the reminder hook is wired under `PostToolUse` in $path — and ONLY there. Returns
     * whether it changed. Idempotent by CONTENT: it computes the desired settings and writes only
     * when they differ, so it can never leave a stray/duplicate copy (an old `UserPromptSubmit` one)
     * behind, no matter what state it starts from.
     */
    public static function wire(string $path): bool
    {
        /** @var array<string, mixed> $settings */
        $settings = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        $before = json_encode($settings);

        $hooks = is_array($settings['hooks'] ?? null) ? $settings['hooks'] : [];

        // Strip our remind hook from EVERY event — every other hook, in any event, is preserved.
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

        // …then add exactly one, under PostToolUse.
        $hooks['PostToolUse'][] = ['hooks' => [['type' => 'command', 'command' => self::COMMAND]]];
        $settings['hooks'] = $hooks;

        if (json_encode($settings) === $before) {
            return false;
        }

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return true;
    }

    /**
     * Is $command OUR reminder hook — a `commandments … remind` invocation? Matched by both tokens
     * so it recognises every form the command has taken (`commandments remind`, the quoted
     * `commandments" remind` anchored at `$CLAUDE_PROJECT_DIR`), and never the `commandments sync`
     * hook. This is how a stale wiring is found and replaced.
     */
    private static function isOurs(string $command): bool
    {
        return str_contains($command, 'commandments') && str_contains($command, 'remind');
    }
}

