<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Support\Directory;

/**
 * Taking a STRANDED session folder into the one a session actually reads — a stretch written before the
 * session had a name, or from a checkout that could not see it, left in a hash folder nothing will open
 * again. Whatever the folder HOLDS moves, rather than a list of files we happen to know about, so a
 * `reports/` directory travels with the counters. Anything present on both sides is KEPT and the source
 * left standing, since deleting what did not come across is the loss this exists to prevent.
 */
final class Adoption
{
    /**
     * Entries taken whole, relative to the folder being adopted.
     *
     * @var list<string>
     */
    private array $moved = [];

    /**
     * Entries left where they were — a collision nothing knows how to merge, or a move that failed.
     *
     * @var list<string>
     */
    private array $kept = [];

    private function __construct(private readonly string $from) {}

    /**
     * Take everything $from holds into $into, and remove $from once nothing is left behind.
     */
    public static function take(string $from, string $into): self
    {
        $adoption = new self($from);

        $adoption->absorb($from, $into);
        $adoption->settle();

        return $adoption;
    }

    /**
     * Did everything come across? A folder that answers false is still standing, holding exactly what
     * {@see kept} names.
     */
    public function isComplete(): bool
    {
        return $this->kept === [];
    }

    /**
     * @return list<string>
     */
    public function moved(): array
    {
        return $this->moved;
    }

    /**
     * @return list<string>
     */
    public function kept(): array
    {
        return $this->kept;
    }

    /**
     * How many entries came across, either way — what a caller says out loud.
     */
    public function count(): int
    {
        return count($this->moved);
    }

    private function absorb(string $from, string $into): void
    {
        if (! is_dir($from)) {
            return;
        }

        @mkdir($into, 0777, true);

        foreach (Directory::entries($from) as $entry) {
            $this->adopt($entry, $into . '/' . basename($entry));
        }
    }

    /**
     * Take one entry across. Nothing at the target means the entry itself moves — the cheap case, and
     * the one a folder of hook counters is made of.
     */
    private function adopt(string $entry, string $target): void
    {
        // A link is REMOVED from no tree and walked through in none: what it points at is not this
        // folder's to move, and following one would carry a stranger's file into the session's record.
        if (is_link($entry)) {
            $this->kept[] = $this->named($entry);

            return;
        }

        if (! file_exists($target)) {
            $this->record($entry, self::move($entry, $target));

            return;
        }

        if (is_dir($entry) && is_dir($target)) {
            $this->absorb($entry, $target);

            return;
        }

        $this->kept[] = $this->named($entry);
    }

    private function record(string $entry, bool $succeeded): void
    {
        if (! $succeeded) {
            $this->kept[] = $this->named($entry);

            return;
        }

        $this->moved[] = $this->named($entry);
    }

    /**
     * Drop the emptied folder — but ONLY once every entry is accounted for elsewhere. A folder holding
     * something that did not move is left exactly as it stands: a stub holding half of what was there is
     * worse than the folder it came from, because it reads as complete.
     */
    private function settle(): void
    {
        if (! $this->isComplete()) {
            return;
        }

        Directory::delete($this->from);
    }

    /**
     * $path as it reads to a person — relative to the folder being adopted, since every entry shares
     * the same absolute head.
     */
    private function named(string $path): string
    {
        return substr($path, strlen($this->from) + 1);
    }

    /**
     * Move $from to $to, whole. A rename is atomic and is what happens inside one `.commandments`
     * folder; the copy is for the day the two sit on different filesystems, where a rename answers
     * false rather than throwing.
     */
    private static function move(string $from, string $to): bool
    {
        if (@rename($from, $to)) {
            return true;
        }

        if (is_dir($from)) {
            return Directory::copy($from, $to) && Directory::delete($from);
        }

        return @copy($from, $to) && @unlink($from);
    }
}
