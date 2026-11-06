<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\Option;

/**
 * An exclusive hold on a file, for the stretch that must be ONE act — several processes write our state
 * at once (a hook fires per tool call, in every worktree), and a claim that is read and then written is
 * not a claim. {@see on} makes the folder first, because a lock is taken BEFORE anything is written and
 * so cannot lean on the folder a later write creates; it answers absent when the hold cannot be taken at
 * all, which is the caller's to judge — a courtesy lock carries on, a claim refuses.
 */
final class Lock
{
    /**
     * @param  resource  $handle
     */
    private function __construct(private $handle) {}

    /**
     * Take an exclusive hold on $path, WAITING for whoever has it rather than interleaving with them.
     *
     * @return Option<self>
     */
    public static function on(string $path): Option
    {
        @mkdir(dirname($path), 0777, true);

        $handle = @fopen($path, 'c');

        if ($handle === false) {
            return Option::none();
        }

        flock($handle, LOCK_EX);

        return Option::some(new self($handle));
    }

    public function release(): void
    {
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
    }
}
