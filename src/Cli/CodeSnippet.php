<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Renders a fenced, line-numbered excerpt of a source file around a reported line — so a
 * `report` issue carries the flagged code itself, not just a `file:line` that points into a
 * private or since-changed consumer tree the maintainer can't open. Pure text extraction for
 * a human to read; the reported line is marked with `→` and the gutter maps each row back to
 * the `:line` in the report.
 */
final class CodeSnippet
{
    /** Lines of context shown before the reported line (declarations read downward). */
    private const int BEFORE = 3;

    /** Lines shown after the reported line — enough to carry a small method/class body. */
    private const int AFTER = 24;

    public function forFile(string $file, ?int $line): ?string
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false || $lines === []) {
            return null;
        }

        $total = count($lines);
        $target = $line ?? 1;
        $start = max(1, $target - self::BEFORE);
        $end = min($total, $target + self::AFTER);
        $width = strlen((string) $end);

        $rows = [];

        for ($n = $start; $n <= $end; $n++) {
            $marker = ($line !== null && $n === $target) ? '→' : ' ';
            $rows[] = sprintf("%s %{$width}d  %s", $marker, $n, $lines[$n - 1]);
        }

        $where = $line !== null ? "{$file}:{$line}" : $file;

        return "**Code** (`{$where}`):\n\n"
            . "```{$this->fence($file)}\n"
            . implode("\n", $rows)
            . "\n```\n";
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
