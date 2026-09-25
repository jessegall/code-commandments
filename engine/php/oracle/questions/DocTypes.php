<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\Support\DocType;
use PhpParser\Node;

/** Every node's docblock, and the collection element a field's docblock declares, resolved through the file's imports. */
final class DocTypes implements Question
{
    public function name(): string
    {
        return 'doctypes';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            yield [$node, 'doc', $node->getDocComment()?->getText()];
            $variable = match (true) {
                $node instanceof Node\Param && $node->var instanceof Node\Expr\Variable && is_string($node->var->name) => $node->var->name,
                $node instanceof Node\Stmt\Property => null,
                default => false,
            };
            if ($variable === false || $node->getDocComment() === null) {
                continue;
            }
            $element = DocType::elementNamed($node->getDocComment()->getText(), $variable);
            yield [$node, 'element', ['element' => $element, 'resolved' => $element === null ? null : DocType::resolve($element, $file)]];
        }
        foreach (nodes($file->ast) as $node) {
            if ($node instanceof Node\Name) {
                yield [$node, 'resolve', DocType::resolve($node->getLast(), $file)];
            }
        }
    }
}
