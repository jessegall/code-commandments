<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * The to-do list a `TodoWrite` call just wrote — the one thing about a long run of work the USER can
 * actually see. Read semantically so a {@see Hook} never pokes at the payload's array keys: what is
 * in progress right now ({@see current}), where it sits on the list ({@see position}), and whether the
 * list LEADS with it ({@see leadsWithCurrent}) — a list led by the current item answers "what is it
 * doing right now?" in one line, where one burying it at #7 has to be scanned.
 */
final class TodoList
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function __construct(private readonly array $items) {}

    /**
     * The `todos` a `TodoWrite` payload carries, ignoring anything that isn't a list of items.
     */
    public static function from(mixed $todos): self
    {
        if (! is_array($todos)) {
            return new self([]);
        }

        return new self(array_values(array_filter($todos, is_array(...))));
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * What the agent says it is doing right now — the first `in_progress` item, as the user reads it
     * (its `activeForm` when the list carries one, else its content). Empty when nothing is in progress:
     * a list of pending work is not a claim about the current moment, so there is nothing to lead with.
     */
    public function current(): string
    {
        $item = $this->items[$this->index()] ?? null;

        if ($item === null) {
            return '';
        }

        return (string) ($item['activeForm'] ?? '') ?: (string) ($item['content'] ?? '');
    }

    /**
     * Where the in-progress item sits, counting from 1 — the number the user would have to scan down to.
     * Zero when nothing is in progress.
     */
    public function position(): int
    {
        return $this->index() + 1;
    }

    /**
     * Does the list lead with what is happening NOW? True when the first item is the in-progress one —
     * and true when nothing is in progress, because then no item is being buried.
     */
    public function leadsWithCurrent(): bool
    {
        return $this->index() <= 0;
    }

    /**
     * The offset of the first `in_progress` item, or -1 when there is none.
     */
    private function index(): int
    {
        foreach ($this->items as $offset => $item) {
            if (($item['status'] ?? '') === 'in_progress') {
                return $offset;
            }
        }

        return -1;
    }
}
