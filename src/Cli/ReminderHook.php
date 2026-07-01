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
     * Ensure the reminder hook is wired under `PostToolUse` in $path. Returns whether it changed.
     */
    public static function wire(string $path): bool
    {
        /** @var array<string, mixed> $settings */
        $settings = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        $hooks = is_array($settings['hooks'] ?? null) ? $settings['hooks'] : [];

        $alreadyCurrent = false;

        // Strip every existing remind hook from every event (migrating off UserPromptSubmit).
        foreach (['UserPromptSubmit', 'PostToolUse'] as $event) {
            $groups = is_array($hooks[$event] ?? null) ? $hooks[$event] : [];
            $rebuilt = [];

            foreach ($groups as $group) {
                if (! is_array($group) || ! is_array($group['hooks'] ?? null)) {
                    $rebuilt[] = $group;

                    continue;
                }

                $kept = [];

                foreach ($group['hooks'] as $hook) {
                    $command = is_array($hook) ? (string) ($hook['command'] ?? '') : '';

                    if (str_contains($command, 'commandments remind')) {
                        $alreadyCurrent = $alreadyCurrent || ($event === 'PostToolUse' && $command === self::COMMAND);

                        continue;
                    }

                    $kept[] = $hook;
                }

                if ($kept !== []) {
                    $group['hooks'] = $kept;
                    $rebuilt[] = $group;
                }
            }

            if ($rebuilt === []) {
                unset($hooks[$event]);
            } else {
                $hooks[$event] = $rebuilt;
            }
        }

        if ($alreadyCurrent) {
            return false;
        }

        $postToolUse = is_array($hooks['PostToolUse'] ?? null) ? $hooks['PostToolUse'] : [];
        $postToolUse[] = ['hooks' => [['type' => 'command', 'command' => self::COMMAND]]];
        $hooks['PostToolUse'] = $postToolUse;
        $settings['hooks'] = $hooks;

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return true;
    }
}
