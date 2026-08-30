<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;

/**
 * Whether a scheduler is watching the dispatch list. The SCHEDULER says so itself, as its first act and
 * again when it stops — nobody else can answer honestly, and a mark the orchestrator wrote on its behalf
 * would say a scheduler is alive because somebody once meant to start one.
 */
final readonly class Watching
{
    public function __construct(private StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.watching'), self::legend()));
    }

    private static function legend(): Legend
    {
        return new Legend(
            'Whether a scheduler is watching the dispatch list, said by the scheduler itself. Deleting it '
                . 'means the next tool use asks for one to be started, which is the safe way to be wrong.',
            ['since' => 'when the scheduler said it had started watching — empty when none is'],
            defaults: new State(since: ''),
        );
    }

    public function isWatching(): bool
    {
        return $this->file->read()->text('since') !== '';
    }

    public function since(): string
    {
        return $this->file->read()->text('since');
    }

    public function started(string $at): void
    {
        $this->file->write($this->file->read()->with(since: $at));
    }

    public function stopped(): void
    {
        $this->file->write($this->file->read()->with(since: ''));
    }
}
