<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * The sessions a project has had, and which of them the user is currently looking at. A hook always knows
 * its own session; a human at a terminal knows none, so they are shown the list and MOUNT one, which every
 * later `commandments journal` reads until they choose another. The list is built from the TRANSCRIPTS
 * rather than from anything the journal recorded, so a session that ran before this existed can still be
 * read back.
 */
final class Sessions
{
    /**
     * Where the harness keeps a project's transcripts, under the user's home. The project's own path names
     * the folder, with each separator flattened to a dash.
     */
    private const string TRANSCRIPTS = '/.claude/projects/';

    public function __construct(
        private readonly string $root,
        private readonly StateFile $file,
    ) {}

    public static function of(Workspace $workspace): self
    {
        return new self($workspace->root(), new StateFile($workspace->shared('journal-mount'), self::legend()));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'Which session `commandments journal` is reading when a HUMAN runs it. A hook knows its own '
                . 'session; a person picks one from `commandments journal sessions` and it is kept here '
                . 'until they pick another.',
            ['mounted' => 'the session id every `commandments journal` command reads, until `journal use <id>` changes it'],
            defaults: new State(mounted: ''),
            safe: 'the next `commandments journal` simply asks which session you meant',
        );
    }

    /**
     * Every session this project has had, most recently written first.
     *
     * @return list<Session>
     */
    public function all(): array
    {
        $sessions = [];

        foreach (glob($this->directory() . '/*.jsonl') ?: [] as $path) {
            $transcript = new Transcript($path);

            $sessions[] = new Session(
                basename($path, '.jsonl'),
                $path,
                filemtime($path) ?: 0,
                $transcript->name(),
            );
        }

        usort($sessions, fn (Session $a, Session $b) => $b->at <=> $a->at);

        return $sessions;
    }

    /**
     * The session $handle names — a full id, or as much of one as is unambiguous in a printed list.
     *
     * @return Option<Session>
     */
    public function named(string $handle): Option
    {
        foreach ($this->all() as $session) {
            if ($session->answersTo($handle)) {
                return Option::some($session);
            }
        }

        return Option::none();
    }

    /**
     * The session being read right now: the one this process belongs to when a hook or an agent is running,
     * else the one the user mounted. Absent when a human has not chosen yet — which is the moment to show
     * them the list rather than guess.
     *
     * @return Option<Session>
     */
    public function current(): Option
    {
        $live = getenv('CLAUDE_CODE_SESSION_ID');

        if (is_string($live) && $live !== '') {
            return $this->named($live)->orElse(fn () => $this->mounted());
        }

        return $this->mounted();
    }

    /**
     * @return Option<Session>
     */
    public function mounted(): Option
    {
        return $this->named($this->file->read()->text('mounted'));
    }

    /**
     * Read this session from now on.
     */
    public function mount(Session $session): void
    {
        $this->file->write($this->file->read()->with(mounted: $session->id));
    }

    /**
     * Where this project's transcripts live — `~/.claude/projects/<the project's path, dashed>`.
     */
    private function directory(): string
    {
        return (getenv('HOME') ?: '~') . self::TRANSCRIPTS . str_replace('/', '-', $this->root);
    }
}
