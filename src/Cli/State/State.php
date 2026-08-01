<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

/**
 * The contents of a {@see StateFile}, decoded — NAMED values ("held-stops: 3"), and, for a file that
 * keeps one, the list of things beneath them (the conditions of a gate, the constraints of a plan).
 *
 * Values are read and written BY NAME, as PHP named arguments:
 *
 *     new State(head: $head, total_nudges: 0)          // a whole state, written fresh
 *     $state->with(stuck: true, stuck_at: $head)       // the same state, adjusted
 *     $state->int('total_nudges')                      // read back
 *
 * PHP identifiers cannot carry a dash, so a name is spelled with UNDERSCORES in code and rendered with
 * dashes in the file (`total_nudges` → `total-nudges`); {@see name} is the one place that is decided,
 * and reads accept either spelling. Immutable: every change returns a new state, so a caller reads,
 * adjusts and writes back in one line instead of reassembling a positional line array and hoping the
 * order still matches the reader.
 *
 * Absence is modelled as the empty state, never as a missing file a caller has to test for — every
 * read of a name that isn't there answers with its default.
 */
final class State
{
    /**
     * How a true flag is written — the file says `yes`/`no` rather than a bare `1` a reader has to
     * interpret.
     */
    private const string YES = 'yes';

    /**
     * @var array<string, string>  name → value, in the order they are written
     */
    private array $values = [];

    /**
     * @var list<string>  the list section's lines, verbatim
     */
    private array $items = [];

    /**
     * The schema this state answers to, once it belongs to a file — every name read or written is
     * checked against it, so a typo throws where it happens rather than reading back as a default
     * forever. Null for a state built in the air; {@see StateFile::write} checks that one.
     */
    private ?Legend $legend = null;

    /**
     * Where the state came from, for the message a bad name raises.
     */
    private string $origin = '';

    public function __construct(string|int|bool ...$values)
    {
        foreach ($values as $name => $value) {
            $this->values[self::name((string) $name)] = self::render($value);
        }
    }

    /**
     * A state from values already decoded — how {@see StateFile} rebuilds one it has just read.
     *
     * @param  array<string, string>  $values  keyed by the name as it appears in the file
     * @param  list<string>  $items
     */
    public static function of(array $values, array $items = []): self
    {
        $state = new self;
        $state->values = $values;
        $state->items = array_values($items);

        return $state;
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * @return list<string>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Bind this state to the schema of the file it came from, so every later read and adjustment is
     * checked against what that file declares.
     */
    public function declaredBy(Legend $legend, string $origin): self
    {
        $state = clone $this;
        $state->legend = $legend;
        $state->origin = $origin;

        return $state;
    }

    public function has(string $name): bool
    {
        return array_key_exists($this->declared($name), $this->values);
    }

    public function text(string $name, string $default = ''): string
    {
        return $this->values[$this->declared($name)] ?? $default;
    }

    public function int(string $name, int $default = 0): int
    {
        return $this->has($name) ? (int) $this->text($name) : $default;
    }

    /**
     * A yes/no value — {@see render} writes a bool as `yes`/`no`, so the file says what it means.
     */
    public function flag(string $name): bool
    {
        return $this->text($name) === self::YES;
    }

    /**
     * The same state with $values set — `$state->with(stuck: true, stuck_at: $head)`. Every other
     * value, and the list, carry over untouched.
     */
    public function with(string|int|bool ...$values): self
    {
        $state = clone $this;

        foreach ($values as $name => $value) {
            $state->values[$this->declared((string) $name)] = self::render($value);
        }

        return $state;
    }

    /**
     * This state with $other laid over it — $other's values win, and its list is taken whole. How a
     * write starts from a file's declared defaults and keeps their order ({@see Legend::defaults}).
     */
    public function merge(self $other): self
    {
        $state = clone $this;
        $state->values = [...$this->values, ...$other->values];
        $state->items = $other->items;

        return $state;
    }

    /**
     * @param  list<string>  $items
     */
    public function withItems(array $items): self
    {
        $state = clone $this;
        $state->items = array_values($items);

        return $state;
    }

    /**
     * A name as the FILE spells it: `total_nudges` (a PHP argument name) and `total-nudges` (the file)
     * are the same value, so a caller may write either.
     */
    public static function name(string $name): string
    {
        return str_replace('_', '-', $name);
    }

    /**
     * $name as the file spells it, refused when the file's {@see Legend} does not declare it.
     */
    private function declared(string $name): string
    {
        $name = self::name($name);

        if ($this->legend !== null && ! $this->legend->declares($name)) {
            throw UnknownValue::for($name, $this->legend->names(), $this->origin);
        }

        return $name;
    }

    private static function render(string|int|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? self::YES : 'no';
        }

        return Line::flatten((string) $value);
    }
}
