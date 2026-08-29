<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Orchestration;

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
    public function __construct(
        public string $item,
        public string $argv,
        public int $exitCode,
        public string $head,
        public string $mergeBase,
        public string $at,
        public string $output = '',
    ) {}

    /**
     * Did the verification pass? The exit code answers, because it is the thing the process actually said.
     */
    public function isGreen(): bool
    {
        return $this->exitCode === 0;
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
        $verdict = $this->isGreen() ? 'green' : "FAILED (exit {$this->exitCode})";

        return implode("\n", [
            "  {$verdict} — {$this->argv}, read {$this->at}",
            "  tree {$this->head} · merge-base {$this->mergeBase}",
        ]);
    }

    /**
     * A receipt as one stored line — the fields tab-separated, since none of them may contain a tab.
     */
    public function toLine(): string
    {
        return implode("\t", [
            $this->item, $this->argv, (string) $this->exitCode, $this->head, $this->mergeBase, $this->at,
        ]);
    }

    /**
     * The receipt $line records, absent for a line this format did not write.
     *
     * @return Option<self>
     */
    public static function fromLine(string $line): Option
    {
        $fields = explode("\t", $line, 6);

        if (count($fields) !== 6) {
            return Option::none();
        }

        [$item, $argv, $exitCode, $head, $mergeBase, $at] = $fields;

        return Option::some(new self($item, $argv, (int) $exitCode, $head, $mergeBase, $at));
    }
}
