<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Session;

use JesseGall\CodeCommandments\Workspace;

/**
 * The sessions a project has had. Built from the TRANSCRIPTS the harness writes, so a session that ran
 * before this package was installed is still listed — which is what lets `session list` say what a folder
 * named after a hash actually IS.
 */
final class Sessions
{
    /**
     * Where the harness keeps a project's transcripts, under the user's home. The project's own path names
     * the folder, with each separator flattened to a dash.
     */
    private const string TRANSCRIPTS = '/.claude/projects/';

    public function __construct(private readonly string $root) {}

    public static function of(Workspace $workspace): self
    {
        return new self($workspace->root());
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
     * Where this project's transcripts live — `~/.claude/projects/<the project's path, dashed>`.
     */
    private function directory(): string
    {
        return (getenv('HOME') ?: '~') . self::TRANSCRIPTS . str_replace('/', '-', $this->root);
    }
}
