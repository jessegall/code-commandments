<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Report\Redactor;

/**
 * Renders a fenced, line-numbered excerpt of a source file around a reported line — so a
 * `report` issue carries the flagged code itself, not just a `file:line` that points into a
 * private or since-changed consumer tree the maintainer can't open. Pure text extraction for
 * a human to read; the reported line is marked with `→` and the gutter maps each row back to
 * the `:line` in the report. Every captured line is passed through {@see Redactor} first, so a
 * secret in the consumer's source never travels into the (often public) issue.
 */
final class CodeSnippet
{
    /**
     * Lines of context shown before the reported line (declarations read downward).
     */
    private const int BEFORE = 3;

    /**
     * Lines shown after the reported line — enough to carry a small method/class body.
     */
    private const int AFTER = 24;

    public function __construct(private readonly Redactor $redactor = new Redactor()) {}

    /**
     * A fenced excerpt around $line — or, when $endLine is given, around the whole `$line..$endLine`
     * RANGE (every line in it marked `→`), with a little context on each side.
     */
    public function forFile(string $file, ?int $line, ?int $endLine = null): ?string
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false || $lines === []) {
            return null;
        }

        $total = count($lines);
        $from = $line ?? 1;
        $to = max($from, $endLine ?? $from);
        $start = max(1, $from - self::BEFORE);
        $end = min($total, $to + ($endLine !== null ? self::BEFORE : self::AFTER));
        $width = strlen((string) $end);

        $rows = [];

        for ($n = $start; $n <= $end; $n++) {
            $marker = ($line !== null && $n >= $from && $n <= $to) ? '→' : ' ';
            $rows[] = sprintf("%s %{$width}d  %s", $marker, $n, $this->redactor->line($lines[$n - 1]));
        }

        return "**Code** (`{$this->where($file, $line, $endLine)}`):\n\n"
            . "```{$this->fence($file)}\n"
            . implode("\n", $rows)
            . "\n```\n";
    }

    private function where(string $file, ?int $line, ?int $endLine): string
    {
        if ($line === null) {
            return $file;
        }

        return $endLine !== null && $endLine > $line ? "{$file}:{$line}-{$endLine}" : "{$file}:{$line}";
    }

    private function fence(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'php' => 'php',
            'vue' => 'vue',
            'ts', 'mts', 'cts' => 'ts',
            'js', 'mjs', 'cjs' => 'js',
            default => '',
        };
    }
}
