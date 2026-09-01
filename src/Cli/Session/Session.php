<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Session;

use JesseGall\CodeCommandments\Workspace;

/**
 * One session a human can look back at — its id, the transcript holding it, when it was last written to,
 * and the name it goes by. Enough to choose from a list, and nothing more: the conversation itself is read
 * from the transcript when one is picked.
 */
final readonly class Session
{
    public function __construct(
        public string $id,
        public string $path,
        public int $at,
        public string $name,
    ) {}

    /**
     * The folder this session's state is filed under — the name `commandments session` prints, which is a
     * hash of the id and so looks nothing like it.
     */
    public function key(): string
    {
        return Workspace::keyFor($this->id);
    }
}
