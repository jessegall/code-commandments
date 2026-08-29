<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

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

    public function transcript(): Transcript
    {
        return new Transcript($this->path);
    }

    /**
     * How this session reads in a list of them — when it was last written to, and what it was about.
     */
    public function describe(): string
    {
        return sprintf(
            '%s  %s  %s',
            $this->key(),
            date('Y-m-d H:i', $this->at),
            $this->name === '' ? '(nothing said yet)' : $this->name,
        );
    }

    /**
     * The folder this session's state is filed under — the name `commandments session` prints, which is a
     * hash of the id and so looks nothing like it.
     */
    public function key(): string
    {
        return Workspace::keyFor($this->id);
    }

    /**
     * Does this session answer to $handle? A session wears two names — the id the transcript is called
     * after, and the hashed folder its state lives in — and a person reading either one back off their
     * screen should not have to know which they are holding. Both answer, and a prefix of the id does too.
     */
    public function answersTo(string $handle): bool
    {
        return $handle !== '' && (str_starts_with($this->id, $handle) || $this->key() === $handle);
    }
}
