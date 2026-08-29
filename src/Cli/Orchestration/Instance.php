<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * Which profile this session is running, and since when. The profile is the durable half and lives in
 * git; this is the live half and belongs to the session — so a restart correctly loses what was bound to
 * a process and keeps what was written down.
 */
final class Instance
{
    public function __construct(private readonly StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.orchestrating'), self::legend()));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'The orchestration this session is running (`commandments orchestrate use <profile>`). The '
                . 'profile itself lives in `.commandments/orchestrator/profiles/` and is committed; this '
                . 'says which one is in force here, and dies with the session as it should.',
            [
                'profile' => 'the profile this session is working under',
                'since' => 'when it started',
            ],
            defaults: new State(profile: '', since: ''),
            safe: 'the session is no longer orchestrating under any profile',
        );
    }

    /**
     * The profile in force, absent when this session is not orchestrating.
     *
     * @return Option<string>
     */
    public function profile(): Option
    {
        return Option::fromTruthy($this->file->read()->text('profile'));
    }

    public function since(): string
    {
        return $this->file->read()->text('since');
    }

    public function start(string $profile, string $at): void
    {
        $this->file->write($this->file->read()->with(profile: $profile, since: $at));
    }

    public function stop(): void
    {
        $this->file->delete();
    }

    public function isRunning(): bool
    {
        return $this->profile()->isSome();
    }
}
