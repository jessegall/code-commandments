<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

/**
 * What a {@see StateFile} is, written for the HUMAN who finds it — the prose, the meaning of every
 * named value, and what the list beneath them holds. It is DECLARED once beside the state it
 * describes and every write {@see render}s from it, so a file carries its own key and a value can
 * never arrive in one without its meaning.
 */
final class Legend
{
    /**
     * @param  string  $about  what this file is, in prose — the first thing a human reads
     * @param  array<string, string>  $variables  each named value → what it means
     * @param  string|null  $list  what the list section holds; none for a file that keeps no list
     * @param  string  $safe  what deleting the file costs, completing "Safe to delete — …"
     */
    public function __construct(
        private readonly string $about,
        private readonly array $variables,
        private readonly ?State $defaults = null,
        private readonly ?string $list = null,
        private readonly string $safe = 'it regenerates',
    ) {}

    /**
     * The state a file holds before anything has happened to it — declared here so every write starts
     * from the WHOLE state, in this order, rather than from whatever the last caller happened to touch.
     * A file that only ever had `last_id` set would otherwise show only that, and a human reading it
     * could not tell an unset value from a missing feature.
     */
    public function defaults(): State
    {
        return $this->defaults ?? new State;
    }

    /**
     * Does this file keep a list section — a block of things (conditions, constraints) rather than
     * named values? It decides the file's SHAPE, so it is declared here with the meaning of the
     * lines, never guessed from whether the list happens to be empty right now.
     */
    public function hasList(): bool
    {
        return $this->list !== null;
    }

    /**
     * Every value name this file documents, as the file spells it — what a written state is checked
     * against, so a value can never be persisted without its meaning.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(State::name(...), array_keys($this->variables));
    }

    /**
     * Is $name part of this file's state at all? The legend is the SCHEMA: a value it does not declare
     * cannot be written or read ({@see UnknownValue}), so a typo fails loudly instead of landing in the
     * file under a name nothing will ever read back.
     */
    public function declares(string $name): bool
    {
        return in_array(State::name($name), $this->names(), true);
    }

    /**
     * The explanation written beneath the state: the prose, then every value name against its
     * meaning, then what the list holds, then what deleting it costs.
     */
    public function render(): string
    {
        $blocks = [$this->about];

        if ($this->variables !== []) {
            $blocks[] = $this->key(); // A file may keep only a list; there is then no key to write.
        }

        if ($this->hasList()) {
            $blocks[] = "Between the dividers is the list: {$this->list}";
        }

        $blocks[] = "Safe to delete — {$this->safe}.";

        return implode("\n\n", $blocks);
    }

    /**
     * The named values, one per line, aligned so the file reads as a key. Only called where there ARE
     * some — a file that keeps a list and nothing else has no key, and writing an empty heading for one
     * would describe a block the file does not have.
     */
    private function key(): string
    {
        $names = array_map(State::name(...), array_keys($this->variables));
        $width = max(array_map(strlen(...), $names));
        $lines = ["The block above the first divider is this file's state, one `name: value` per line:"];

        foreach (array_combine($names, $this->variables) as $name => $meaning) {
            $lines[] = '  ' . str_pad($name, $width) . '  ' . $meaning;
        }

        return implode("\n", $lines);
    }
}
