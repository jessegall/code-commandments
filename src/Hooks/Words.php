<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

use JesseGall\PhpTypes\Option;

/**
 * The words of one shell command, read as a shell reads them. It exists so that "the word after `-C`"
 * and "the word this command opens with" are ASKED rather than indexed: a missing word is genuinely
 * absent — the flag was written with no value, or the line is empty — and an index filled with `''`
 * turns that absence into a directory nobody named.
 */
final readonly class Words
{
    /**
     * @param  list<string>  $words
     */
    private function __construct(private array $words) {}

    public static function of(string $line): self
    {
        $split = preg_split('/\s+/', trim($line)) ?: [];

        return new self(array_values(array_filter($split, static fn (string $word): bool => $word !== '')));
    }

    /**
     * Is this the command being run — the word at the HEAD, never a word anywhere in the line. A command
     * that merely names another is a different act, which is the distinction a gate refusing its own
     * commit message had lost.
     */
    public function opens(string $word): bool
    {
        return $this->first()->isSomeAnd(static fn (string $head): bool => $head === $word);
    }

    /**
     * @return Option<string>
     */
    public function first(): Option
    {
        return Option::fromNullable($this->words[0] ?? null);
    }

    /**
     * What follows $word, absent when nothing does — a flag written with no value says nothing about a
     * place, and a caller told '' would resolve one anyway.
     *
     * @return Option<string>
     */
    public function after(string $word): Option
    {
        foreach ($this->words as $index => $found) {
            if ($found === $word) {
                return Option::fromNullable($this->words[$index + 1] ?? null);
            }
        }

        return Option::none();
    }

    public function has(string $word): bool
    {
        return in_array($word, $this->words, true);
    }
}
