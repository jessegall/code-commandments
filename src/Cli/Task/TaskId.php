<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Task;

use JesseGall\PhpTypes\Option;

/**
 * A task's ADDRESS — `002`, or `002.1` for a subtask of it. The number is what makes a task quotable:
 * an orchestrator hands a worker `002.1` and both know exactly which file that is, which a folder path
 * could not survive being pasted into a brief. A subtask carries its parent's number rather than living
 * under its folder, so the relationship travels with the name and nothing has to be moved to express it.
 */
final readonly class TaskId
{
    /**
     * How wide a top-level number is written. `010` sorting beside `002` in a plain directory listing is
     * the whole reason for the padding — a bare `10` files itself between `1` and `2`.
     */
    private const int WIDTH = 3;

    private const string SEPARATOR = '.';

    /**
     * @param  list<int>  $numbers  outermost first — `[2, 1]` is `002.1`. Empty is the board itself.
     */
    private function __construct(public array $numbers) {}

    /**
     * The address of the BOARD — what every top-level task is a child of. It names no task, which is
     * exactly why it exists: numbering and parentage then have one rule instead of a top-level special
     * case beside a subtask one.
     */
    public static function board(): self
    {
        return new self([]);
    }

    public static function of(int ...$numbers): self
    {
        return new self(array_values($numbers));
    }

    /**
     * $text read as an address — `2`, `002` and `002.1` all parse. None when a segment is not a positive
     * number, so a mistyped id is refused where it is typed rather than resolving to nothing later.
     *
     * @return Option<self>
     */
    public static function parse(string $text): Option
    {
        $numbers = [];

        foreach (explode(self::SEPARATOR, trim($text)) as $segment) {
            if ($segment === '' || ! ctype_digit($segment) || (int) $segment < 1) {
                return Option::none();
            }

            $numbers[] = (int) $segment;
        }

        return Option::some(new self($numbers));
    }

    /**
     * The $number-th task beneath this one — `board()->child(3)` is `003`, `002->child(1)` is `002.1`.
     */
    public function child(int $number): self
    {
        return new self([...$this->numbers, $number]);
    }

    /**
     * Is this a DIRECT subtask of $other? Direct rather than anywhere-beneath, because that is what
     * numbering asks: the next free number under a parent depends on its own children and on nothing
     * their children did.
     */
    public function isChildOf(self $other): bool
    {
        return count($this->numbers) === count($other->numbers) + 1
            && array_slice($this->numbers, 0, count($other->numbers)) === $other->numbers;
    }

    /**
     * How deep the address goes — 1 for a top-level task, 2 for its subtask. What a listing indents by.
     */
    public function depth(): int
    {
        return count($this->numbers);
    }

    /**
     * The last segment — the number this task was given beneath its parent.
     */
    public function number(): int
    {
        return $this->numbers === [] ? 0 : $this->numbers[count($this->numbers) - 1];
    }

    public function equals(self $other): bool
    {
        return $this->numbers === $other->numbers;
    }

    /**
     * Address order — `001`, `001.1`, `002`. A parent sorts immediately above its own subtasks, so ONE
     * ordered listing reads as the tree and nothing has to be grouped or re-nested to show it.
     *
     * Segment by segment, because PHP compares two arrays by LENGTH first: `[1, 1] <=> [2]` answers
     * "greater", which files every subtask below every top-level task and loses the shape entirely.
     */
    public static function compare(self $a, self $b): int
    {
        $shared = min(count($a->numbers), count($b->numbers));

        for ($depth = 0; $depth < $shared; $depth++) {
            $order = $a->numbers[$depth] <=> $b->numbers[$depth];

            if ($order !== 0) {
                return $order;
            }
        }

        return count($a->numbers) <=> count($b->numbers);
    }

    /**
     * The address as it is written — in a filename, in a listing, and in the brief a worker is handed.
     */
    public function render(): string
    {
        if ($this->numbers === []) {
            return '';
        }

        $rendered = str_pad((string) $this->numbers[0], self::WIDTH, '0', STR_PAD_LEFT);

        foreach (array_slice($this->numbers, 1) as $number) {
            $rendered .= self::SEPARATOR . $number;
        }

        return $rendered;
    }
}
