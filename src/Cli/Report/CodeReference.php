<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Report;

/**
 * One code origin a `report` points at — a file and, optionally, the line or line RANGE that
 * matters. Parsed from a `--ref` value (`path`, `path:42`, `path:40-58`); the {@see Report}
 * command reads the referenced source and injects it into the issue, so a maintainer sees the
 * actual code, not just a `file:line` into a private consumer tree. A report may carry MANY of
 * these — one per file a bug spans.
 */
final readonly class CodeReference
{
    private function __construct(
        public string $path,
        public ?int $startLine,
        public ?int $endLine,
    ) {}

    /**
     * How this reference is written back — `path`, `path:42`, `path:40-58`. The round trip of
     * {@see parse}, so the issue body never re-derives the spelling it was given.
     */
    public function label(): string
    {
        if ($this->startLine === null) {
            return $this->path;
        }

        $span = $this->endLine === null ? '' : "-{$this->endLine}";

        return "{$this->path}:{$this->startLine}{$span}";
    }

    /**
     * Parse a `--ref` value: `path`, `path:LINE`, or `path:START-END`. The line spec is the tail
     * after the LAST colon and only when it's numeric (or a numeric range) — so a bare path, even
     * an unusual one, is never mis-split. A blank value yields null.
     */
    public static function parse(string $value): ?self
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $colon = strrpos($value, ':');

        if ($colon === false) {
            return new self($value, null, null);
        }

        $path = substr($value, 0, $colon);
        $spec = substr($value, $colon + 1);
        [$start, $end] = self::span($spec);

        // The tail wasn't a line spec (a path that itself contains a colon) — keep the whole value.
        if ($start === null) {
            return new self($value, null, null);
        }

        return new self($path, $start, $end);
    }

    /**
     * A line spec `N` or `N-M` as `[start, end]`, or `[null, null]` when it isn't numeric.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function span(string $spec): array
    {
        $dash = strpos($spec, '-');

        if ($dash === false) {
            return ctype_digit($spec) && $spec !== '' ? [(int) $spec, null] : [null, null];
        }

        $from = substr($spec, 0, $dash);
        $to = substr($spec, $dash + 1);

        if (! ctype_digit($from) || ! ctype_digit($to) || $from === '' || $to === '') {
            return [null, null];
        }

        return [(int) $from, (int) $to];
    }
}
