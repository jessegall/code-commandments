<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

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
        return sprintf('%s  %s', date('Y-m-d H:i', $this->at), $this->name === '' ? '(nothing said yet)' : $this->name);
    }

    /**
     * Does this session answer to $handle — its full id, or the short prefix a menu prints?
     */
    public function answersTo(string $handle): bool
    {
        return $handle !== '' && str_starts_with($this->id, $handle);
    }
}
