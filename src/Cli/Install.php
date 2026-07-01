<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments install` — wire a consumer up once. Adds a `commandments sync`
 * call to the consumer's composer `post-update-cmd` and `post-install-cmd` so the
 * skills + CLAUDE.md briefing refresh automatically on every `composer
 * update`/`install`, wires a `PostToolUse` hook that surfaces the cardinal rule once
 * every 25 tool uses, then runs an initial sync. Idempotent.
 */
final class Install
{
    private const string HOOK = '@php vendor/bin/commandments sync';

    // Anchored at $CLAUDE_PROJECT_DIR (the absolute project root the harness gives
    // every hook) — a relative `vendor/bin/...` silently dies when Claude's working
    // directory isn't the project root, and the reminder never reaches the agent.
    private const string REMIND_HOOK = 'php "$CLAUDE_PROJECT_DIR/vendor/bin/commandments" remind';

    public function run(array $args): int
    {
        $composerPath = getcwd() . '/composer.json';

        if (! is_file($composerPath)) {
            fwrite(STDERR, "No composer.json in " . getcwd() . "\n");

            return 2;
        }

        $wired = $this->wireComposerScripts($composerPath);
        $reminded = $this->wireReminderHook(getcwd() . '/.claude/settings.json');

        fwrite(STDOUT, $wired
            ? "✓ Wired `commandments sync` into composer post-update-cmd / post-install-cmd.\n"
            : "✓ composer hooks already wired.\n");

        fwrite(STDOUT, $reminded
            ? "✓ Wired the trace-to-the-source reminder into the PostToolUse hook (every 25 tool uses).\n"
            : "✓ PostToolUse reminder already wired.\n");

        return (new Sync)->run($args);
    }

    /**
     * Register `commandments remind` as a `PostToolUse` hook so the cardinal rule surfaces once
     * every 25 tool uses. Idempotent, and MIGRATING: we remove ONLY our own remind hook — matched
     * by its command containing `commandments remind` — from both events (so an older one wired
     * under `UserPromptSubmit` moves over), then add the single current group under `PostToolUse`.
     * Every other hook the project has, in any event, is preserved untouched — we never strip a
     * hook we didn't write.
     */
    private function wireReminderHook(string $path): bool
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
                        $alreadyCurrent = $alreadyCurrent || ($event === 'PostToolUse' && $command === self::REMIND_HOOK);

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
        $postToolUse[] = ['hooks' => [['type' => 'command', 'command' => self::REMIND_HOOK]]];
        $hooks['PostToolUse'] = $postToolUse;
        $settings['hooks'] = $hooks;

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return true;
    }

    private function wireComposerScripts(string $path): bool
    {
        /** @var array<string, mixed> $composer */
        $composer = json_decode((string) file_get_contents($path), true);
        $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
        $changed = false;

        foreach (['post-update-cmd', 'post-install-cmd'] as $event) {
            $hooks = $this->asList($scripts[$event] ?? []);

            if (! in_array(self::HOOK, $hooks, true)) {
                $hooks[] = self::HOOK;
                $scripts[$event] = $hooks;
                $changed = true;
            }
        }

        if ($changed) {
            $composer['scripts'] = $scripts;
            file_put_contents($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        }

        return $changed;
    }

    /**
     * @return list<string>
     */
    private function asList(mixed $value): array
    {
        return match (true) {
            is_array($value) => array_values(array_filter($value, 'is_string')),
            is_string($value) => [$value],
            default => [],
        };
    }
}
