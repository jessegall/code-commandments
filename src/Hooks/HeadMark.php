<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;

/**
 * The commit a checkout was last seen at. A `git commit` tool call is a MOMENT, not a fact about a
 * checkout — it fires just as loudly for a commit made in a lane or in another repository the shell
 * stepped into — so what a trigger wants is narrower, did HEAD move HERE, and no payload carries that.
 * Session-scoped, in the shared {@see StateFile} format, so the sha on disk says whose it is.
 */
final readonly class HeadMark
{
    public function __construct(private StateFile $file) {}

    /**
     * The mark $slug keeps in $workspace — its own file, since it belongs to no larger state and
     * outliving the thing it marks is exactly the failure that costs a dispatch.
     */
    public static function named(Workspace $workspace, string $slug, string $describe = ''): self
    {
        return new self(new StateFile($workspace->path('.' . $slug . '-head'), self::legend($slug, $describe)));
    }

    private static function legend(string $slug, string $describe): Legend
    {
        return new Legend(
            "Code-commandments mark `{$slug}` — the commit this checkout was last seen at. Deleting it "
                . 'only means the next commit here counts as new.',
            ['head' => 'the sha this last answered for' . ($describe === '' ? '' : ". It {$describe}")],
            defaults: new State(head: ''),
        );
    }

    /**
     * Has HEAD moved to $head since this last marked it? Answering yes MARKS it, so one commit is
     * answered ONCE however many times its moment fires — and a moment raised by a commit that landed
     * somewhere else is answered not at all.
     *
     * An empty $head is not a move: it means nothing could be read, and a checkout that cannot say
     * where it is has not been shown to have moved.
     */
    public function movedTo(string $head): bool
    {
        if ($head === '') {
            return false;
        }

        $state = $this->file->read();

        if ($state->text('head') === $head) {
            return false;
        }

        $this->file->write($state->with(head: $head));

        return true;
    }

    /**
     * The sha this stands at, empty when nothing has been marked.
     */
    public function head(): string
    {
        return $this->file->read()->text('head');
    }
}
