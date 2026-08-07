<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Span;
use PhpParser\Node;

/**
 * A parsed source file: its path, its name-resolved, parent-linked AST, and its
 * original source. The raw `source` is retained so a finding can expose its byte
 * range AS a {@see \JesseGall\CodeCommandments\Span} the scribe layer rewrites
 * through (see {@see NodeMatch::span()}).
 */
final class ParsedFile
{
    /**
     * @param  list<Node>  $ast
     */
    public function __construct(
        public readonly string $path,
        public readonly array $ast,
        public readonly string $source = '',
    ) {}

    /**
     * The `[$start, $end)` byte range of THIS file, as the {@see Span} the scribe layer rewrites
     * through. The path and the source are one fact about one file, so nothing else pairs them by
     * hand.
     */
    public function span(int $start, int $end): Span
    {
        return new Span($this->path, $this->source, $start, $end);
    }
}
