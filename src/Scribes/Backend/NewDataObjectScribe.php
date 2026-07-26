<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\DataClassShape;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;

/**
 * Rewrites `new Data()` to `::from()` where provably equivalent (class visible, no
 * input-name remap, resolvable arguments).
 */
final class NewDataObjectScribe extends RepentScribe implements NeedsCodebase
{
    private ?DataClassShape $shape = null;

    public function withCodebase(Codebase $codebase): void
    {
        $this->shape = DataClassShape::forCodebase($codebase);
    }

    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match) => $this->toFrom($match))
            ->rewrites();
    }

    private function toFrom(NodeMatch $match): ?string
    {
        $new = $match->node;

        if ($this->shape === null || ! $new instanceof New_ || ! $new->class instanceof Name) {
            return null;
        }

        $fqcn = $new->class->toString();
        $class = $this->shape->classFor($fqcn);

        // Unverifiable shape, or an input-name remap — can't guarantee the keys.
        if ($class === null || $this->shape->remapsInputNames($fqcn)) {
            return null;
        }

        $params = AstNode::constructorParamsOf($class);
        $source = $match->file->source;
        $entries = [];

        foreach ($new->args as $index => $arg) {
            $key = $arg->name?->toString() ?? AstNode::promotedParamName($params, $index);

            if ($arg->unpack || $key === null) {
                return null;
            }

            $value = Span::slice($source, $arg->value->getStartFilePos(), $arg->value->getEndFilePos());
            $entries[] = "'{$key}' => {$value}";
        }

        $class = Span::slice($source, $new->class->getStartFilePos(), $new->class->getEndFilePos());

        return "{$class}::from([" . $this->body($entries, $new, $source) . '])';
    }

    /**
     * The array's inner text. A call the author wrote across several lines keeps that layout — one entry
     * per line at the original arguments' indent, closing at the original `)`'s — so a 15-key `new` doesn't
     * collapse into one unreadable 700-character line. A single-line call stays on one line.
     *
     * @param  list<string>  $entries
     */
    private function body(array $entries, New_ $new, string $source): string
    {
        $first = $new->args[0] ?? null;
        $inner = $first === null ? null : Span::ownLineIndent($source, $first->getStartFilePos());
        $outer = Span::ownLineIndent($source, $new->getEndFilePos());

        if ($inner === null || $outer === null || $new->getStartLine() === $new->getEndLine()) {
            return implode(', ', $entries);
        }

        return "\n" . implode('', array_map(static fn (string $entry): string => "{$inner}{$entry},\n", $entries)) . $outer;
    }

}
