<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Workspace;
use JesseGall\CodeCommandments\Cli\Scope\GitFiles;

/**
 * A directory with a `vendor/` of its own, and the version of this package installed IN IT — read now,
 * from that checkout's own lockfile, never from a sibling and never from the version of the process
 * asking. The project is one of these and so is every lane, because a worktree keeps the vendor it was
 * SEEDED with, which is how a root update leaves four checkouts judging by last week's rules while
 * answering questions about this week's.
 */
final readonly class Checkout
{
    /**
     * This package, as composer knows it — the one name `upgrade` moves and `lane list` reads.
     */
    public const string PACKAGE = 'jessegall/code-commandments';

    /**
     * Where lanes go when nothing says otherwise, and the profile setting that moves them.
     */
    private const string LANES_FOLDER = '.lanes';

    private const string LANES = 'lanes';

    /**
     * Nothing is installed here at all. A lane in that state runs its gates against nothing.
     */
    public const string NO_VENDOR = 'no vendor';

    /**
     * There is a vendor, and this package is not in it.
     */
    public const string ABSENT = 'not installed';

    /**
     * A vendor entry with no version in it — read, and unreadable, which is not the same as absent.
     */
    public const string UNKNOWN = 'unknown';

    public function __construct(public string $path) {}

    /**
     * Where this project's lanes live — what the profile in force says, else beside the repository. A
     * relative setting resolves against the ROOT rather than the cwd, so a lane opened from a
     * subdirectory does not land inside it.
     *
     * It lives HERE because a lane's home is a fact about lanes, and two callers asking two different
     * objects would be two declarations of one location.
     */
    public static function homeFor(Workspace $workspace, string $root): string
    {
        foreach (Profiles::inForce($workspace) as $profile) {
            foreach ($profile->settings(self::LANES) as $declared) {
                $at = is_string($declared['at'] ?? null) ? $declared['at'] : '';

                if ($at !== '') {
                    return str_starts_with($at, '/') ? $at : $root . '/' . $at;
                }
            }
        }

        return $root . '/' . self::LANES_FOLDER;
    }

    /**
     * Every LANE of the project at $root — each worktree is a checkout of its own, and the main one is
     * the project rather than a lane.
     *
     * @return list<self>
     */
    public static function lanesOf(string $root, GitFiles $git): array
    {
        return array_map(static fn (string $path): self => new self($path), $git->worktrees($root));
    }

    /**
     * What this checkout is called — the lane name, which is also the branch `lane open` cut for it.
     */
    public function name(): string
    {
        return basename($this->path);
    }

    /**
     * Which version of this package this checkout runs, read from its OWN lockfile. That is the whole
     * point: asking the running process would answer for the checkout the process happens to be standing
     * in, which is the confusion this command exists to end.
     */
    public function version(): string
    {
        $installed = $this->path . '/vendor/composer/installed.json';

        if (! is_file($installed)) {
            return self::NO_VENDOR;
        }

        $packages = json_decode((string) file_get_contents($installed), true);

        foreach ($packages['packages'] ?? [] as $package) {
            if (($package['name'] ?? '') === self::PACKAGE) {
                return (string) ($package['version'] ?? self::UNKNOWN);
            }
        }

        return self::ABSENT;
    }

    /**
     * Is this package installed here at all? What `upgrade` has to answer before it moves anything, since
     * `composer update` on a package a project does not require reports success and changes nothing.
     */
    public function hasThePackage(): bool
    {
        return ! in_array($this->version(), [self::NO_VENDOR, self::ABSENT], true);
    }

    /**
     * Is this checkout the package ITSELF, rather than a project that installs it? Composer never puts a
     * package into its own vendor, so {@see ABSENT} is the literal truth in this one repository and a
     * misleading answer — it reads as a project that forgot to install something. Measured from the
     * manifest's own name, never assumed from a path.
     */
    public function isThePackage(): bool
    {
        $manifest = $this->path . '/composer.json';

        if (! is_file($manifest)) {
            return false;
        }

        $declared = json_decode((string) file_get_contents($manifest), true);

        return is_array($declared) && ($declared['name'] ?? '') === self::PACKAGE;
    }

    /**
     * Stand this checkout up with the project's own script, and answer what it exited. What makes a
     * worktree usable is per-project — a vendor copy, node_modules, a database, a port block — so the
     * package runs the file and does not guess its contents.
     */
    public function prepareWith(string $script): int
    {
        $ran = 0;

        passthru(
            'cd ' . escapeshellarg($this->path)
            . ' && sh ' . escapeshellarg($script)
            . ' ' . escapeshellarg($this->path)
            . ' ' . escapeshellarg($this->name()),
            $ran,
        );

        return $ran;
    }

    /**
     * How it reads in a table — whether it agrees with $against, what it runs, where it is, and whatever
     * the caller has to say about it. `lane list` and `upgrade` print the same row deliberately: the
     * moment a reader most wants that table is the moment something has just moved.
     */
    public function row(string $against, string $note = ''): string
    {
        $version = $this->version();

        return rtrim(sprintf('%s %-14s %-44s %s', $version === $against ? ' ' : '!', $version, $this->path, $note));
    }
}
