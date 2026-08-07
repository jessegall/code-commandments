<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Span;

/**
 * The fluent rewrite builder for {@see RepentScribe} — the detector query's mirror. Composes
 * {@see from}, {@see map}/{@see collapse}, {@see replace}/{@see create}, {@see rewrites} to map
 * `path => content`. Engine-agnostic via {@see Span}; pure — returns data, never writes.
 */
final class Draft
{
    /**
     * @var list<mixed>  the working items — the findings, possibly mapped
     */
    private array $items;

    /**
     * @var array<string, string>  path => that file's original source
     */
    private array $sources = [];

    /**
     * @var array<string, list<Edit>>  path => the replacements drafted against it
     */
    private array $edits = [];

    /**
     * @var array<string, string>  path => content of a freshly drafted file
     */
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

            if ($replacement !== null) {
                $this->edit($item->span(), $replacement);
            }
        }

        return $this;
    }

    /**
     * Draft a new file per item — the closure returns `[path, content]` (null skips).
     *
     * @param  callable(mixed): ?array{0: string, 1: string}  $file
     */
    public function create(callable $file): self
    {
        foreach ($this->items as $item) {
            $drafted = $file($item);

            if ($drafted !== null) {
                $this->add($drafted[0], $drafted[1]);
            }
        }

        return $this;
    }

    /**
     * Replace one byte span with new text — the imperative primitive behind {@see replace},
     * for when a strategy needs the span explicit (e.g. the call site it lifts a
     * component OUT of, distinct from the new file it creates).
     */
    public function edit(Span $span, string $text): self
    {
        $this->sources[$span->path] = $span->source;
        $edit = new Edit($span->start, $span->end, $text);

        // Identical edits collapse — two occurrences importing the same component at
        // the same offset is one import, not two.
        if (! array_any($this->edits[$span->path] ?? [], $edit->equals(...))) {
            $this->edits[$span->path][] = $edit;
        }

        return $this;
    }

    /**
     * Draft a new file — the imperative primitive behind {@see create}. A path already
     * drafted is uniquified (`Foo.vue` → `Foo2.vue`) so nothing clobbers.
     */
    public function add(string $path, string $content): self
    {
        $this->creates[$this->free($path)] = $content;

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
            usort($edits, Edit::lastFirst(...));

            $source = $this->sources[$path];
            $consumed = strlen($source) + 1;

            foreach ($edits as $edit) {
                if ($edit->end > $consumed) {
                    continue; // overlaps a region already rewritten (a nested finding)
                }

                $source = $edit->appliedTo($source);
                $consumed = $edit->start;
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
