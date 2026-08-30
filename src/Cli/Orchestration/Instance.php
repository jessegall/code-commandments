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
                'routine_at' => 'how much the session had SAID when the routine last spoke — -1 until it '
                    . 'has spoken at all, since a session that has said nothing yet has still not heard '
                    . 'it. A routine '
                    . 'repeated at a stop where nothing has happened is the nudge that teaches skimming, '
                    . 'so it stays quiet until there is something to have come to rest from',
                'at' => 'where in the plan this session is standing — a `/`-joined path of sidequest '
                    . 'names, empty at the plan itself. Only the CURSOR is session state; the plan it '
                    . 'points into is a folder tree that outlives any one reading of it',
            ],
            defaults: new State(profile: '', since: '', at: '', routine_at: -1),
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

    /**
     * Where in the plan this session is standing, as a path of sidequest names. Empty at the plan
     * itself.
     *
     * @return list<string>
     */
    public function at(): array
    {
        $at = $this->file->read()->text('at');

        return $at === '' ? [] : explode('/', $at);
    }

    /**
     * Stand at $path. The cursor is the one part of a plan that belongs to the session — which level an
     * orchestrator is working is a fact about it, not about the work.
     *
     * @param  list<string>  $path
     */
    public function standAt(array $path): void
    {
        $state = $this->file->read();

        $this->file->write($state->with(at: implode('/', $path)));
    }

    /**
     * Has anything been SAID since the routine last spoke? A checklist repeated at a stop where nothing
     * has happened is a nudge with nothing new in it, and one of those every time teaches a reader to
     * skim the block that will eventually hold something.
     *
     * Answering yes RECORDS this firing, so the routine speaks once per stretch of work rather than once
     * per stop.
     */
    public function routineIsDue(int $said): bool
    {
        $state = $this->file->read();

        if ($said <= $state->int('routine_at', -1)) {
            return false;
        }

        $this->file->write($state->with(routine_at: $said));

        return true;
    }
}
