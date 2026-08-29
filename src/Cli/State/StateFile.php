<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

use JesseGall\CodeCommandments\Support\File;

/**
 * The ONE format every session-scoped state file is written in — the stop gate, the plan marker, the
 * plan's constraints and testing choice, every hook counter. An owner declares WHAT its values mean
 * in its own {@see Legend} and this owns how they are read and written ({@see write} draws the
 * layout), so the format is stated once; everything one feature knows lives in ONE file, so lifting
 * it deletes all of that state at once.
 */
final class StateFile
{
    private const string DIVIDER = '-----';

    private const string ASSIGN = ': ';

    public function __construct(
        private readonly string $path,
        private readonly Legend $legend,
    ) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The decoded state — the empty state when the file doesn't exist, so absence is never a special
     * case a caller has to handle.
     */
    public function read(): State
    {
        if (! is_file($this->path)) {
            return new State;
        }

        $sections = $this->sections((string) file_get_contents($this->path));

        return State::of($this->values($sections[0] ?? []), $this->list($sections))->declaredBy($this->legend, $this->path);
    }

    /**
     * Write $state, then the list section (when this file keeps one), then the legend — three
     * sections divided by `-----`, or two where the legend declares no list. Creates the folder, and
     * goes through {@see File::write} so a reader concurrent with the write sees one whole file or the
     * other — hooks write these from several processes at once.
     *
     *   name: value          ← the state, NAMED (never a positional line a reader must count to)
     *   -----
     *   a thing              ← the list, when this file keeps one (conditions, constraints)
     *   -----
     *   the {@see Legend}    ← what all of it means, and that deleting the file is safe
     */
    public function write(State $state): void
    {
        $state = $this->legend->defaults()->merge($this->declared($state));
        $sections = [$this->assignments($state)];

        if ($this->legend->hasList()) {
            $sections[] = $state->items();
        }

        $sections[] = [$this->legend->render()];

        @mkdir(dirname($this->path), 0777, true);
        File::write($this->path, $this->join($sections));
    }

    public function delete(): void
    {
        @unlink($this->path);
    }

    /**
     * The file split at its dividers, each section as its non-blank lines. The LAST section is the
     * legend — prose for a human, never read back — so it is dropped here and the rest keep their
     * order: values first, then the list when there is one.
     *
     * @return list<list<string>>
     */
    private function sections(string $contents): array
    {
        $sections = [];
        $current = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if ($line === self::DIVIDER) {
                $sections[] = $current;
                $current = [];

                continue;
            }

            if (trim($line) !== '') {
                $current[] = $line;
            }
        }

        return $sections; // $current is the legend — everything after the last divider.
    }

    /**
     * The value lines decoded — keeping only the names this file's {@see Legend} declares, so a value
     * left behind by an older shape of the state is dropped on the next write rather than travelling
     * forward forever under a name nothing reads.
     *
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private function values(array $lines): array
    {
        $values = [];

        foreach ($lines as $line) {
            $at = strpos($line, self::ASSIGN);
            $name = $at === false ? '' : substr($line, 0, $at);

            if ($name !== '' && $this->legend->declares($name)) {
                $values[$name] = substr($line, $at + strlen(self::ASSIGN));
            }
        }

        return $values;
    }

    /**
     * $state, checked against the legend: every value it carries must be one this file DECLARES, so
     * a typo or an undocumented addition throws here where it is written.
     */
    private function declared(State $state): State
    {
        foreach (array_keys($state->values()) as $name) {
            if (! $this->legend->declares($name)) {
                throw UnknownValue::for($name, $this->legend->names(), $this->path);
            }
        }

        return $state;
    }

    /**
     * The list section — the one BETWEEN the values and the legend. A file whose legend declares no
     * list has none, and a file written before its first list entry simply has an empty one.
     *
     * @param  list<list<string>>  $sections
     * @return list<string>
     */
    private function list(array $sections): array
    {
        return $this->legend->hasList() ? ($sections[1] ?? []) : [];
    }

    /**
     * @return list<string>
     */
    private function assignments(State $state): array
    {
        return $state->assignments(self::ASSIGN);
    }

    /**
     * @param  list<list<string>>  $sections
     */
    private function join(array $sections): string
    {
        $lines = [];

        foreach ($sections as $section) {
            $lines = [...$lines, ...$section, self::DIVIDER];
        }

        array_pop($lines); // The legend closes the file; nothing follows it to divide from.

        return implode("\n", $lines) . "\n";
    }
}
