<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Support\Binary;

/**
 * The `commandments sync` call in a project's composer `post-install-cmd` / `post-update-cmd`, which
 * keeps the skills, the briefing and the hooks current on every upgrade. Written by {@see Install}
 * and re-asserted by {@see Sync}, because it is the one part of the wiring that cannot repair
 * itself: while it is missing or names the wrong file, nothing of ours runs to notice.
 */
final class ComposerScripts
{
    private const array EVENTS = ['post-update-cmd', 'post-install-cmd'];

    public function __construct(private readonly string $root) {}

    /**
     * Ensure both events call `commandments sync`, at the path this project really has
     * ({@see Binary}). Returns true when the manifest changed, null when it cannot be READ as JSON
     * — a BOM, a stray comment, a half-written save — which is left alone and reported rather than
     * rewritten from nothing.
     */
    public function ensure(): ?bool
    {
        $path = "{$this->root}/composer.json";
        $composer = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($composer)) {
            return null;
        }

        $call = '@php ' . Binary::in($this->root) . ' sync';
        $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
        $changed = false;

        foreach (self::EVENTS as $event) {
            $hooks = $this->asList($scripts[$event] ?? []);
            $kept = array_values(array_filter($hooks, fn (string $hook): bool => ! $this->isOurs($hook)));

            if ([...$kept, $call] !== $hooks) {
                $scripts[$event] = [...$kept, $call];
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
     * Is $hook a sync call of OURS — so a stale one (a path that no longer exists, an older form) is
     * replaced rather than left beside the current one, and a script the project wrote is untouched.
     */
    private function isOurs(string $hook): bool
    {
        return str_contains($hook, 'commandments') && str_ends_with(rtrim($hook), ' sync');
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
