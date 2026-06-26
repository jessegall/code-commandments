<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * `commandments install` — wire a consumer up once. Adds a `commandments sync`
 * call to the consumer's composer `post-update-cmd` and `post-install-cmd` so the
 * skills + CLAUDE.md briefing refresh automatically on every `composer
 * update`/`install`, then runs an initial sync. Idempotent.
 */
final class Install
{
    private const string HOOK = '@php vendor/bin/commandments sync';

    public function run(array $args): int
    {
        $composerPath = getcwd() . '/composer.json';

        if (! is_file($composerPath)) {
            fwrite(STDERR, "No composer.json in " . getcwd() . "\n");

            return 2;
        }

        $wired = $this->wireComposerScripts($composerPath);
        $this->ensureGitignored(getcwd() . '/.gitignore');

        fwrite(STDOUT, $wired
            ? "✓ Wired `commandments sync` into composer post-update-cmd / post-install-cmd.\n"
            : "✓ composer hooks already wired.\n");

        return (new Sync)->run($args);
    }

    /**
     * Published skills are regenerated on every sync and the judge checklist is a
     * transient working file — keep both out of the repo.
     */
    private function ensureGitignored(string $path): void
    {
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $entries = [
            '# code-commandments published skills (regenerated on composer update)' => '.claude/skills/commandments-*/',
            '# code-commandments judge checklist (transient — regenerated per run)' => 'commandments-sins.md',
        ];

        foreach ($entries as $comment => $entry) {
            if (str_contains($existing, $entry)) {
                continue;
            }

            $prefix = ($existing !== '' && ! str_ends_with($existing, "\n")) ? "\n" : '';
            $existing .= $prefix . "\n{$comment}\n{$entry}\n";
        }

        file_put_contents($path, $existing);
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
