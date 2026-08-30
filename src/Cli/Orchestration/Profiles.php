<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * The ways of working a project has written down, under `.commandments/orchestrator/profiles/`. They are
 * committed like any other source: a profile is reviewed in a diff, shared between sessions, and is the
 * only part of orchestration that outlives the one that wrote it.
 */
final readonly class Profiles
{
    /**
     * Where they live, under the durable tier — never the session one, since the whole point is that a
     * profile is the half a session cannot take with it.
     */
    private const string FOLDER = Workspace::ORCHESTRATOR . '/profiles';

    public function __construct(private string $root) {}

    public static function of(Workspace $workspace): self
    {
        return new self($workspace->shared(self::FOLDER));
    }

    /**
     * The profile THIS SESSION is working under, absent when it is not orchestrating under one. Two
     * commands need it for the same reason — `lane open` stands a worktree up with it and `upgrade`
     * stands every worktree up again — so the two-step read lives here rather than once in each.
     *
     * @return Option<Profile>
     */
    public static function inForce(Workspace $workspace): Option
    {
        return Instance::inSession($workspace)->profile()
            ->andThen(fn (string $running): Option => self::of($workspace)->named($running));
    }

    /**
     * Every profile written down, by name.
     *
     * @return list<Profile>
     */
    public function all(): array
    {
        $profiles = [];

        foreach (glob($this->root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $profiles[] = new Profile(basename($dir), $dir);
        }

        return $profiles;
    }

    /**
     * The profile called $name, absent when nobody has written one.
     *
     * @return Option<Profile>
     */
    public function named(string $name): Option
    {
        $profile = new Profile($name, $this->root . '/' . $name);

        return $profile->exists() ? Option::some($profile) : Option::none();
    }

    public function folder(): string
    {
        return $this->root;
    }
}
