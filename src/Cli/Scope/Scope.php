<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * Resolves file scope for a command run from `--changes`/`--branch[=BASE]` flags, compounding
 * other FileScopes with AND logic; paths canonicalized to match git change-sets.
 */
final class Scope implements FileScope
{
    /**
     * @param  array<string, true>|null  $files  null = whole codebase; keys are realpaths
     * @param  list<FileScope>  $restrictions  extra scopes AND-ed with the path set (a FrozenScope always)
     */
    private function __construct(private readonly ?array $files, private readonly array $restrictions = []) {}

    /**
     * Parse the scope flags, pick the matching strategy, and resolve it against $path.
     *
     * @throws ScopeUnavailable when a `--changes`/`--branch` scope can't be resolved.
     */
    public static function fromArgs(array $args, string $path): self
    {
        $strategy = match (true) {
            ($id = self::repent($args)) !== null => new ChecklistScope($id),
            ($base = self::branch($args)) !== null => new BranchChanges($base),
            self::flag($args, '--changes') => new WorkingTreeChanges,
            default => new EntireCodebase,
        };

        return new self(self::canonical($strategy->restrictTo($path)), self::always());
    }

    /**
     * An unscoped scope — includes every file except the frozen ones.
     */
    public static function everything(): self
    {
        return new self(null, self::always());
    }

    /**
     * A scope restricted to the given files (canonicalized to realpaths).
     *
     * @param  list<string>  $files
     */
    public static function restrictedTo(array $files): self
    {
        return new self(self::canonical(array_fill_keys($files, true)), self::always());
    }

    /**
     * Is $file in scope — a file this run may flag or rewrite? Every compounded scope must admit it, and
     * an unscoped path set admits everything the restrictions allow.
     */
    public function includes(string $file): bool
    {
        foreach ($this->restrictions as $restriction) {
            if (! $restriction->includes($file)) {
                return false;
            }
        }

        if ($this->files === null) {
            return true;
        }

        $real = realpath($file);

        return $real !== false && isset($this->files[$real]);
    }

    /**
     * Are ALL of these files in scope — the gate a cross-file (chain) rewrite passes as a whole? An
     * existing file that isn't a target (frozen, or out of scope) forbids the lot, so nothing is
     * half-applied or written into a file this run must not touch. A file that doesn't exist yet is a
     * fresh creation (an extracted component) and is always allowed.
     *
     * @param  list<string>  $files
     */
    public function permits(array $files): bool
    {
        foreach ($files as $file) {
            if (file_exists($file) && ! $this->includes($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The scopes compounded into EVERY run — the frozen exclusion is always among a run's targets rules.
     *
     * @return list<FileScope>
     */
    private static function always(): array
    {
        return [new FrozenScope()];
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

    /**
     * The `--repent[=ID|latest]` value (a bare `--repent` means `latest`), or null when
     * absent. Public so a command can both scope to it AND react to it (judge skips
     * writing a checklist when scoped to one — it would clobber the file it's reading).
     */
    public static function repent(array $args): ?string
    {
        foreach ($args as $arg) {
            if ($arg === '--repent') {
                return 'latest';
            }

            if (str_starts_with($arg, '--repent=')) {
                return substr($arg, 9);
            }
        }

        return null;
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
