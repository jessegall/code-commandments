<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

/**
 * Where a finding sits in its file — the path, that file's full source, and the
 * `[start, end)` byte range the finding occupies (end exclusive). It is the seam
 * between the two engines and the {@see Draft} builder: a backend {@see \JesseGall\CodeCommandments\Ast\NodeMatch}
 * and a frontend {@see \JesseGall\CodeCommandments\Vue\ElementMatch} each expose
 * their position AS a `Span`, so a scribe rewrites the same way regardless of engine.
 */
final class Span
{
    public function __construct(
        public readonly string $path,
        public readonly string $source,
        public readonly int $start,
        public readonly int $end,
    ) {}

    /**
     * This span's own slice of the source.
     */
    public function text(): string
    {
        return substr($this->source, $this->start, $this->end - $this->start);
    }

    /**
     * Does this span STRICTLY contain $other — same file, $other's range inside this one,
     * and not the identical range? The geometry behind "is that finding nested in this one".
     */
    public function contains(self $other): bool
    {
        return $this->path === $other->path
            && $this->start <= $other->start
            && $other->end <= $this->end
            && ($this->start !== $other->start || $this->end !== $other->end);
    }

    /**
     * The leading whitespace of the line this span begins on (its indentation), or ''
     * when something non-blank precedes it on that line.
     */
    public function lineIndent(): string
    {
        $lineStart = strrpos(substr($this->source, 0, $this->start), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $prefix = substr($this->source, $lineStart, $this->start - $lineStart);

        return $prefix !== '' && trim($prefix) === '' ? $prefix : '';
    }

    /**
     * This span's text re-indented to sit cleanly at a new base indent — the original
     * column is stripped from every continuation line, then $base is applied. Lifting
     * a nested block out to the top of a new file without dragging its old indentation.
     */
    public function reindent(string $base = '    '): string
    {
        $lineStart = strrpos(substr($this->source, 0, $this->start), "\n");
        $column = $this->start - ($lineStart === false ? 0 : $lineStart + 1);

        $lines = explode("\n", $this->text());
        $out = [$base . $lines[0]];

        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                $out[] = '';

                continue;
            }

            $strip = 0;
            while ($strip < $column && ($line[$strip] ?? '') === ' ') {
                $strip++;
            }

            $out[] = $base . substr($line, $strip);
        }

        return implode("\n", $out);
    }
}
