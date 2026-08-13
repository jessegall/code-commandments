<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

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
     * The substring of $source spanning the INCLUSIVE byte range `[$start, $endInclusive]` — the one
     * sanctioned "cut a node's source out" primitive, so no scribe re-derives `substr($s, $start, $end + 1
     * - $start)` by hand. {@see Writer::slice} adapts it to a php-parser node.
     */
    public static function slice(string $source, int $start, int $endInclusive): string
    {
        return substr($source, $start, $endInclusive + 1 - $start);
    }

    /**
     * The indentation (leading whitespace) of the line $pos sits on. The sanctioned offset-math primitive
     * a scribe uses to align an inserted line — never its own `strrpos("\n")` scan.
     */
    public static function indentAt(string $source, int $pos): string
    {
        $lineStart = self::lineStartAt($source, $pos);

        return substr($source, $lineStart, $pos - $lineStart);
    }

    /**
     * The byte offset the line containing $pos BEGINS at — the start of the source when nothing
     * precedes it. The one place the walk back to a line boundary is written, so a scribe splicing
     * a whole line in front of a declaration never hand-rolls it.
     */
    public static function lineStartAt(string $source, int $pos): int
    {
        $newline = strrpos(substr($source, 0, $pos), "\n");

        return $newline === false ? 0 : $newline + 1;
    }

    /**
     * The byte offset just PAST the line containing $pos — after its newline, or the end of the
     * source when nothing follows. The forward counterpart of {@see lineStartAt}, so a scribe
     * lifting a whole line OUT never hand-rolls the walk to the break.
     */
    public static function lineEndAt(string $source, int $pos): int
    {
        $newline = self::after($source, $pos, "\n");

        return $newline === null ? strlen($source) : $newline + 1;
    }

    /**
     * The 1-based LINE $pos sits on — the offset→line answer every located node needs to report a
     * `file:line`. The one place the newline count is written, so a parse layer that stamps its
     * nodes with byte offsets never carries its own `substr_count` beside the real one.
     */
    public static function lineAt(string $source, int $pos): int
    {
        return substr_count($source, "\n", 0, max(0, min($pos, strlen($source)))) + 1;
    }

    /**
     * The leading whitespace of the line $pos sits on, whatever else precedes $pos on it — the indent the
     * whole line, and so its continuation lines, are laid out against. The wider companion to
     * {@see ownLineIndent}, which answers only "is $pos itself the first thing on its line".
     */
    public static function lineIndentAt(string $source, int $pos): string
    {
        $prefix = self::indentAt($source, $pos);

        return substr($prefix, 0, strlen($prefix) - strlen(ltrim($prefix)));
    }

    /**
     * The byte offset of $needle's LAST occurrence before $pos, or null. The one sanctioned "search back
     * to a delimiter/keyword from an AST position" — so a scribe never hand-rolls `strrpos` over the source.
     */
    public static function before(string $source, int $pos, string $needle): ?int
    {
        $at = strrpos(substr($source, 0, $pos), $needle);

        return $at === false ? null : $at;
    }

    /**
     * The byte offset of $needle's FIRST occurrence at/after $pos, or null — the forward counterpart of
     * {@see before}.
     */
    public static function after(string $source, int $pos, string $needle): ?int
    {
        $at = strpos($source, $needle, $pos);

        return $at === false ? null : $at;
    }

    /**
     * The offset of the first NON-whitespace byte at/after $pos (bounded by $limit, default end of source)
     * — skipping the gap between two tokens without a scribe hand-rolling a `ctype_space` char loop.
     */
    public static function skipWhitespace(string $source, int $pos, ?int $limit = null): int
    {
        $limit ??= strlen($source);
        $pos += strspn($source, " \t\r\n", $pos, max(0, $limit - $pos));

        return $pos;
    }

    /**
     * How a block OPENS in this file — `' {'` when the brace follows the header on the same line, or
     * `"\n{$indent}{"` when it stands on a line of its own (Allman). Read from the very statement being
     * rewritten (the first `{` at/after $pos), so an emitted block wears the file's OWN brace style
     * instead of the one the scribe was written in (#416). The one sanctioned primitive for it, so no
     * scribe hard-codes a brace.
     */
    public static function blockOpener(string $source, int $pos, string $indent): string
    {
        return self::braceOnItsOwnLine($source, $pos) ? "\n{$indent}{" : ' {';
    }

    /**
     * Does the first `{` at/after $pos begin its own line — a newline standing between the header and
     * the brace? The Allman-vs-K&R question itself, for a caller that needs the verdict rather than
     * the opener text {@see blockOpener} builds from it (placing an `else` between two blocks, say).
     */
    public static function braceOnItsOwnLine(string $source, int $pos): bool
    {
        $brace = self::after($source, $pos, '{');

        if ($brace === null) {
            return false;
        }

        return self::ownLineIndent($source, $brace) !== null;
    }

    /**
     * The leading whitespace of the line this span begins on (its indentation), or ''
     * when something non-blank precedes it on that line.
     */
    public function lineIndent(): string
    {
        return self::ownLineIndent($this->source, $this->start) ?? '';
    }

    /**
     * The indentation of the line $pos sits on, but ONLY when $pos is the first non-blank byte on that
     * line — null when code precedes it. The primitive behind "is this token on its own line, and if so
     * at what indent", so a scribe can mirror a multi-line call's layout instead of re-deriving the scan.
     */
    public static function ownLineIndent(string $source, int $pos): ?string
    {
        $lineStart = strrpos(substr($source, 0, $pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $prefix = substr($source, $lineStart, $pos - $lineStart);

        return $prefix !== '' && trim($prefix) === '' ? $prefix : null;
    }

    /**
     * The column this span begins on — the width of the indentation before its first line.
     */
    public function column(): int
    {
        $lineStart = strrpos(substr($this->source, 0, $this->start), "\n");

        return $this->start - ($lineStart === false ? 0 : $lineStart + 1);
    }

    /**
     * This span's text re-indented to sit cleanly at a new base indent — the indentation of the LINE
     * it begins on is stripped from every continuation line, then $base is applied. Lifting a nested
     * block out to the top of a new file without dragging its old indentation.
     *
     * The line's indent, not the span's own {@see column}: a span that begins mid-line (an operand
     * lifted out of `$cond || throw Refused::for(…)` spanning several lines) has a column far to the
     * right of the block its continuation lines are laid out against, and stripping THAT would flatten
     * them instead of shifting them. For a span that starts its line the two are the same number.
     */
    public function reindent(string $base = '    '): string
    {
        return self::reindentText($this->text(), strlen(self::lineIndentAt($this->source, $this->start)), $base);
    }

    /**
     * Re-indent an arbitrary block of $text whose first line sat at $column — so the write
     * engine can splice a span (e.g. drop a directive) and THEN reindent the result.
     */
    public static function reindentText(string $text, int $column, string $base = '    '): string
    {
        $lines = explode("\n", $text);
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
