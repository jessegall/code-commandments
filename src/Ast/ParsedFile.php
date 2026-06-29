<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node;

/**
 * A parsed source file: its path and its name-resolved, parent-linked AST.
 */
final class ParsedFile
{
    /**
     * @param  list<Node>  $ast
     */
    public function __construct(
        public readonly string $path,
        public readonly array $ast,
    ) {}
}
