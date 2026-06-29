<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments install` — wire a consumer up once. Adds a `commandments sync`
 * call to the consumer's composer `post-update-cmd` and `post-install-cmd` so the
 * skills + CLAUDE.md briefing refresh automatically on every `composer
 * update`/`install`, wires a `UserPromptSubmit` hook that re-injects the cardinal
 * rule every turn, then runs an initial sync. Idempotent.
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
            ? "✓ Wired the trace-to-the-source reminder into the UserPromptSubmit hook.\n"
            : "✓ UserPromptSubmit reminder already wired.\n");

        return (new Sync)->run($args);
    }

    /**
     * Register `commandments remind` as a `UserPromptSubmit` hook so the cardinal
     * rule is re-injected on every turn. Idempotent, and MIGRATING: an older remind
     * hook wired with a relative path (which dies off-root) is rewritten to the
     * current cwd-robust command. Other hooks are left intact.
     */
    private function wireReminderHook(string $path): bool
    {
        /** @var array<string, mixed> $settings */
        $settings = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
        $hooks = is_array($settings['hooks'] ?? null) ? $settings['hooks'] : [];
        $prompts = is_array($hooks['UserPromptSubmit'] ?? null) ? $hooks['UserPromptSubmit'] : [];

        $alreadyCurrent = false;
        $rebuilt = [];

        // Strip every existing remind hook; we re-add exactly one current group below.
        // A group left with no hooks is dropped; non-remind hooks are preserved.
        foreach ($prompts as $group) {
            if (! is_array($group) || ! is_array($group['hooks'] ?? null)) {
                $rebuilt[] = $group;

                continue;
            }

            $kept = [];

            foreach ($group['hooks'] as $hook) {
                $command = is_array($hook) ? (string) ($hook['command'] ?? '') : '';

                if (str_contains($command, 'commandments remind')) {
                    $alreadyCurrent = $alreadyCurrent || $command === self::REMIND_HOOK;

                    continue;
                }

                $kept[] = $hook;
            }

            if ($kept !== []) {
                $group['hooks'] = $kept;
                $rebuilt[] = $group;
            }
        }

        if ($alreadyCurrent) {
            return false;
        }

        $rebuilt[] = ['hooks' => [['type' => 'command', 'command' => self::REMIND_HOOK]]];
        $hooks['UserPromptSubmit'] = $rebuilt;
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
