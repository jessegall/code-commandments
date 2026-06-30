<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

/**
 * The fluent rewrite builder a {@see RepentScribe} composes — the scribe side of the
 * engine, and the mirror of the detector's query. A detector hands a scribe its
 * findings; the scribe OPENS a Draft over them and narrows/acts in the same shape a
 * query does: {@see from} opens it, {@see map} / {@see collapse} narrow the items
 * (like `where`/`reject`), {@see replace} / {@see create} describe the change, and
 * {@see rewrites} is the terminal that returns the `path => content` map. The domain
 * decision lives in the closure each step takes, exactly as `where(fn …)` does.
 *
 * It is engine-agnostic: an item is replaced through its own {@see Span} (`->span()`),
 * so a backend {@see \JesseGall\CodeCommandments\Ast\NodeMatch} and a frontend
 * {@see \JesseGall\CodeCommandments\Vue\ElementMatch} build rewrites identically. The
 * mechanics scribes used to hand-roll live here ONCE: edits to a file apply end-first
 * so earlier offsets stay valid, an edit overlapping an already-rewritten region (a
 * nested finding) is skipped, and a new file whose path is taken is uniquified. Pure
 * — it returns data, it never writes.
 */
final class Draft
{
    /** @var list<mixed>  the working items — the findings, possibly mapped */
    private array $items;

    /** @var array<string, string>  path => that file's original source */
    private array $sources = [];

    /** @var array<string, list<array{start: int, end: int, text: string}>> */
    private array $edits = [];

    /** @var array<string, string>  path => content of a freshly drafted file */
    private array $creates = [];

    /**
     * @param  list<mixed>  $items
     */
    private function __construct(array $items)
    {
        $this->items = array_values($items);
    }

    /**
     * Open the builder over a detector's findings.
     *
     * @param  list<mixed>  $findings
     */
    public static function from(array $findings): self
    {
        return new self($findings);
    }

    /**
     * Transform each item, dropping the nulls — e.g. a chain head → the whole chain.
     *
     * @param  callable(mixed): mixed  $transform
     */
    public function map(callable $transform): self
    {
        $this->items = array_values(array_filter(array_map($transform, $this->items), static fn ($item): bool => $item !== null));

        return $this;
    }

    /**
     * Keep only the first item per key — collapse a group of duplicates to one.
     *
     * @param  callable(mixed): string  $key
     */
    public function collapse(callable $key): self
    {
        $seen = [];
        $kept = [];

        foreach ($this->items as $item) {
            $k = $key($item);

            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $kept[] = $item;
            }
        }

        $this->items = $kept;

        return $this;
    }

    /**
     * Replace each item's span with the text the closure returns (null skips it).
     *
     * @param  callable(mixed): ?string  $text
     */
    public function replace(callable $text): self
    {
        foreach ($this->items as $item) {
            $replacement = $text($item);

            if ($replacement === null) {
                continue;
            }

            $span = $item->span();
            $this->sources[$span->path] = $span->source;
            $this->edits[$span->path][] = ['start' => $span->start, 'end' => $span->end, 'text' => $replacement];
        }

        return $this;
    }

    /**
     * Draft a new file per item — the closure returns `[path, content]` (null skips).
     * A path already drafted is uniquified (`Foo.vue` → `Foo2.vue`) so nothing clobbers.
     *
     * @param  callable(mixed): ?array{0: string, 1: string}  $file
     */
    public function create(callable $file): self
    {
        foreach ($this->items as $item) {
            $drafted = $file($item);

            if ($drafted === null) {
                continue;
            }

            [$path, $content] = $drafted;
            $this->creates[$this->free($path)] = $content;
        }

        return $this;
    }

    /**
     * The new content of every file this Draft changes.
     *
     * @return array<string, string>  path => new content
     */
    public function rewrites(): array
    {
        $rewrites = $this->creates;

        foreach ($this->edits as $path => $edits) {
            usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

            $source = $this->sources[$path];
            $consumed = strlen($source) + 1;

            foreach ($edits as $edit) {
                if ($edit['end'] > $consumed) {
                    continue; // overlaps a region already rewritten (a nested finding)
                }

                $source = substr($source, 0, $edit['start']) . $edit['text'] . substr($source, $edit['end']);
                $consumed = $edit['start'];
            }

            $rewrites[$path] = $source;
        }

        return $rewrites;
    }

    /**
     * A path not yet drafted — suffixing `name.ext` → `name2.ext` until it's free.
     */
    private function free(string $path): string
    {
        if (! isset($this->creates[$path])) {
            return $path;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $stem = $extension === '' ? $path : substr($path, 0, -(strlen($extension) + 1));
        $suffix = $extension === '' ? '' : ".{$extension}";

        $n = 2;

        while (isset($this->creates[$stem . $n . $suffix])) {
            $n++;
        }

        return $stem . $n . $suffix;
    }
}
