<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * The resolved file scope of a command run — the single choke-point where the
 * `--changes`/`--git`/`--branch[=BASE]` flags become a concrete set of paths. Every
 * command parses the whole tree for cross-file correctness, then asks the Scope
 * `includes($file)` to decide what it reports on / acts on; an unscoped run includes
 * everything.
 *
 * Paths are canonicalized with `realpath`, so a finding's scanned path (which may be
 * relative or unresolved) matches the git change-set (which is already absolute and
 * symlink-resolved) regardless of how the codebase was addressed.
 */
final class Scope
{
    /**
     * @param  array<string, true>|null  $files  null = whole codebase; keys are realpaths
     */
    private function __construct(private readonly ?array $files) {}

    /**
     * Parse the scope flags, pick the matching strategy, and resolve it against $path.
     *
     * @throws ScopeUnavailable when a `--changes`/`--branch` scope can't be resolved.
     */
    public static function fromArgs(array $args, string $path): self
    {
        $strategy = match (true) {
            ($base = self::branch($args)) !== null => new BranchChanges($base),
            self::flag($args, '--changes', '--git') => new WorkingTreeChanges,
            default => new EntireCodebase,
        };

        return new self(self::canonical($strategy->restrictTo($path)));
    }

    /**
     * An unscoped scope — includes everything.
     */
    public static function everything(): self
    {
        return new self(null);
    }

    /**
     * A scope restricted to the given files (canonicalized to realpaths).
     *
     * @param  list<string>  $files
     */
    public static function restrictedTo(array $files): self
    {
        return new self(self::canonical(array_fill_keys($files, true)));
    }

    /**
     * Is $file in scope? An unscoped scope includes everything.
     */
    public function includes(string $file): bool
    {
        if ($this->files === null) {
            return true;
        }

        $real = realpath($file);

        return $real !== false && isset($this->files[$real]);
    }

    /**
     * Is a scope active (a subset of files), as opposed to the whole codebase?
     */
    public function isScoped(): bool
    {
        return $this->files !== null;
    }

    /**
     * Is a scope active but resolved to NO files (nothing changed)? Always false for
     * an unscoped run.
     */
    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    /**
     * The resolved set (realpath => true), or null when unscoped.
     *
     * @return array<string, true>|null
     */
    public function files(): ?array
    {
        return $this->files;
    }

    /**
     * @param  array<string, true>|null  $files
     * @return array<string, true>|null
     */
    private static function canonical(?array $files): ?array
    {
        if ($files === null) {
            return null;
        }

        $set = [];

        foreach (array_keys($files) as $path) {
            $real = realpath($path);
            $set[$real !== false ? $real : $path] = true;
        }

        return $set;
    }

    private static function branch(array $args): ?string
    {
        foreach ($args as $arg) {
            if ($arg === '--branch') {
                return 'main';
            }

            if (str_starts_with($arg, '--branch=')) {
                return substr($arg, 9);
            }
        }

        return null;
    }

    private static function flag(array $args, string ...$names): bool
    {
        foreach ($names as $name) {
            if (in_array($name, $args, true)) {
                return true;
            }
        }

        return false;
    }
}
