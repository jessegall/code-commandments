<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node;

/**
 * A parsed source file: its path, its name-resolved, parent-linked AST, and its
 * original source. The raw `source` is retained so a finding can expose its byte
 * range AS a {@see \JesseGall\CodeCommandments\Scribes\Span} the scribe layer rewrites
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
}
