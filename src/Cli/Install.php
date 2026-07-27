<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;


use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
/**
 * `commandments install` — wire a consumer up once. Adds a `commandments sync`
 * call to the consumer's composer `post-update-cmd` and `post-install-cmd` so the
 * skills + CLAUDE.md briefing refresh automatically on every `composer
 * update`/`install`, wires our Claude Code hooks ({@see Hooks} — the cardinal-rule
 * heartbeat and the "did you judge?" nudge), then runs an initial sync. Idempotent.
 */
final class Install implements Command
{
    private const string HOOK = '@php vendor/bin/commandments sync';

    public function names(): array
    {
        return ['install'];
    }

    public function help(): Help
    {
        return Help::of('Wire a consumer project up once — the composer sync hook, the Claude Code hook suite (cardinal-rule reminder, judge nudge, plan-execution hooks) and .gitignore — then sync.')
            ->form('install', 'wire it (idempotent; every hook we write is stamped, so your own hooks are never touched)');
    }

    public function run(Input $input): int
    {
        $composerPath = getcwd() . '/composer.json';

        if (! is_file($composerPath)) {
            fwrite(STDERR, "No composer.json in " . getcwd() . "\n");

            return 2;
        }

        $wired = $this->wireComposerScripts($composerPath);
        $hooked = HookRegistry::wire(getcwd() . '/.claude/settings.json', HookRegistry::forProject(getcwd()));

        fwrite(STDOUT, $wired
            ? "✓ Wired `commandments sync` into composer post-update-cmd / post-install-cmd.\n"
            : "✓ composer hooks already wired.\n");

        fwrite(STDOUT, $hooked
            ? "✓ Wired the Claude Code hooks: the cardinal-rule reminder (PostToolUse) + the judge nudge (Stop).\n"
            : "✓ Claude Code hooks already wired.\n");

        return (new Sync)->run($input);
    }

    private function wireComposerScripts(string $path): bool
    {
        /**
         * @var array<string, mixed> $composer
         */
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
