<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\PhpTypes\Option;

/**
 * What a tool READ from a process — the command, the code that came back, and the tree it ran in. Only a
 * run produces one, which is what makes its number a measurement rather than a claim. It names the TREE
 * because a lane's number is honest for the lane and wrong for the branch the moment its base predates
 * the last merge, so an unlabelled receipt would reproduce that trap with better provenance; naming the
 * tree is what lets a later receipt SUPERSEDE this one rather than appear to agree with it.
 */
final readonly class Receipt
{
    /**
     * No `--against` was given, so no distance was ever sought.
     */
    public const string NOT_ASKED = '-';

    /**
     * A base was named and git could not answer — a different thing from nobody asking, and the reason
     * the two are not one symbol.
     */
    public const string UNRESOLVED = '?';

    /**
     * @param  ?string  $unmeasurable  what stopped the check from being a measurement at all — a rig that
     *                                 was not up, a dependency missing. Absent when the run was genuine.
     */
    public function __construct(
        public string $item,
        public string $argv,
        public int $exitCode,
        public string $head,
        public string $mergeBase,
        public string $at,
        public string $output = '',
        public ?string $unmeasurable = null,
    ) {}

    /**
     * Did anything actually get measured? A check that could not RUN says nothing about the work, and
     * filing its exit code as a verdict would be a receipt that lies with provenance — worse than no
     * receipt, because a reader trusts a receipt precisely for having been measured.
     */
    public function isMeasurement(): bool
    {
        return $this->unmeasurable === null;
    }

    /**
     * Did the verification pass? The exit code answers, because it is the thing the process actually said.
     */
    public function isGreen(): bool
    {
        return $this->isMeasurement() && $this->exitCode === 0;
    }

    /**
     * The verdict in a word, for a display with room for nothing more. It is ON the receipt because a
     * display asking `isGreen()` gets a yes-or-no from a value with THREE states, and answers "failing"
     * for a check that never ran — the exact lie the third state exists to prevent, on the surface a
     * reader reaches for when they do not yet know what is wrong.
     */
    public function verdict(): string
    {
        return match (true) {
            ! $this->isMeasurement() => 'COULD NOT MEASURE',
            $this->isGreen() => 'measured green',
            default => 'measured FAILING',
        };
    }

    /**
     * Does this receipt measure the same tree as $other? Two receipts of one item that measured different
     * trees do not agree or disagree — the later one supersedes.
     */
    public function measuredTheSameTreeAs(self $other): bool
    {
        return $this->head === $other->head && $this->mergeBase === $other->mergeBase;
    }

    /**
     * How it reads where somebody is deciding whether to accept — the verdict, then what was run, then the
     * tree, because the tree is the part a reader would otherwise assume.
     */
    public function render(): string
    {
        $verdict = match (true) {
            ! $this->isMeasurement() => "COULD NOT MEASURE — {$this->unmeasurable}",
            $this->isGreen() => 'green',
            default => "FAILED (exit {$this->exitCode})",
        };

        return implode("\n", [
            "  {$verdict} — {$this->argv}, read {$this->at}",
            "  tree {$this->head} · " . $this->distance(),
        ]);
    }

    /**
     * How far this tree stands from the base it was measured against. An absence says which absence it
     * is, in words: the receipt exists to stop a lane's number passing as the branch's, so this is the
     * field a reader must not have to guess at.
     */
    private function distance(): string
    {
        return match ($this->mergeBase) {
            self::NOT_ASKED => 'merge-base not asked (no --against)',
            self::UNRESOLVED => 'merge-base could not be resolved',
            default => "merge-base {$this->mergeBase}",
        };
    }

    /**
     * A receipt as one stored line — the fields tab-separated, since none of them may contain a tab.
     */
    public function toLine(): string
    {
        return implode("\t", [
            $this->item, $this->argv, (string) $this->exitCode, $this->head, $this->mergeBase, $this->at,
            $this->unmeasurable ?? '',
        ]);
    }

    /**
     * The receipt $line records, absent for a line this format did not write.
     *
     * @return Option<self>
     */
    public static function fromLine(string $line): Option
    {
        $fields = explode("\t", $line, 7);

        if (count($fields) !== 7) {
            return Option::none();
        }

        [$item, $argv, $exitCode, $head, $mergeBase, $at, $unmeasurable] = $fields;

        return Option::some(new self(
            $item,
            $argv,
            (int) $exitCode,
            $head,
            $mergeBase,
            $at,
            '',
            $unmeasurable === '' ? null : $unmeasurable,
        ));
    }
}
