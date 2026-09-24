<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\CodeCommandments\Cli\Scope\ChangedLines;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * One sin a file holds now, as a hook saw it: the rule, where it is, and whether it lies on a line the
 * working tree changed.
 */
final readonly class SinMark
{
    private function __construct(
        public Sin $sin,
        public Located $match,
        public bool $touched,
    ) {}

    public static function of(Sin $sin, Located $match, ChangedLines $changed): self
    {
        return new self($sin, $match, $changed->covers($match->line()));
    }

    /**
     * The same sin however its line moves: the rule, the file, and what the flagged line says.
     */
    public function id(): string
    {
        return sha1($this->sin->name() . "\0" . $this->match->file() . "\0" . $this->flaggedText());
    }

    /**
     * The rule and where it fires, as the agent is told it.
     */
    public function found(): string
    {
        return $this->sin->name() . ' at ' . $this->match->location();
    }

    /**
     * {@see found()} with the path relative to $root, as the journal shows it.
     */
    public function shownFrom(string $root): string
    {
        return str_replace(rtrim($root, '/') . '/', '', $this->found());
    }

    private function flaggedText(): string
    {
        return trim(implode('', array_slice(file($this->match->file()) ?: [], $this->match->line() - 1, 1)));
    }
}
