<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\PhpTypes\Option;

/**
 * One pinned fact and its STANDING — the number a reader strikes it by, and whether a later pin has
 * corrected it. `remember` is the only mechanism promising to survive a compaction, so it is what an agent
 * reaches for whenever it is afraid of losing something, and the record fills with statements that were
 * true when written; correcting one may never DELETE it, so the newer pin NAMES the older, both stay
 * readable, and only the live one is carried to somebody who would act on it.
 */
final readonly class Pin
{
    /**
     * @param  Option<int>  $supersededBy  the later pin that struck this one; absent while it still stands
     */
    public function __construct(
        public int $number,
        public Entry $entry,
        public Option $supersededBy,
    ) {}

    /**
     * $entries — every pinned entry, oldest first — numbered from 1, with each one's strike found among
     * the later pins that name it. Read as a whole rather than per entry, because whether a pin stands is
     * a fact about the SET: nothing in the pin itself knows it was corrected. The number is a position and
     * survives a strike, because pins are append-only and {@see Journal::bounded} never ages one out — so
     * the number a reader sees today is the number they can strike tomorrow.
     *
     * @param  list<Entry>  $entries
     * @return list<self>
     */
    public static function numbered(array $entries): array
    {
        $struck = [];

        foreach ($entries as $at => $entry) {
            foreach ($entry->supersedes() as $number) {
                $struck[$number] = $at + 1;
            }
        }

        $pins = [];

        foreach ($entries as $at => $entry) {
            $number = $at + 1;
            $pins[] = new self($number, $entry, Option::fromNullable($struck[$number] ?? null));
        }

        return $pins;
    }

    /**
     * Is this still what the session believes? Only a live pin reaches a compacted reader — the block it
     * wakes to is on a measured byte budget, and a fact that has been corrected may not spend it.
     */
    public function isLive(): bool
    {
        return $this->supersededBy->isNone();
    }

    public function text(): string
    {
        return $this->entry->text;
    }

    /**
     * How this pin reads in a listing — its number, then what it says, then the correction either way, so
     * the reader sees the fact and its history in one place rather than having to reconcile two lines.
     */
    public function render(): string
    {
        $strike = $this->supersededBy->mapOr('', fn (int $pin) => "  ✗ superseded by pin {$pin}");
        $replaces = $this->entry->supersedes()->mapOr('', fn (int $pin) => "  (supersedes pin {$pin})");

        return $this->entry->time() . $strike . $replaces . "\n" . $this->text();
    }
}
