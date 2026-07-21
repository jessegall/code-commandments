<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;

/**
 * Rewrites a re-threaded wither into PHP 8.5 clone-with, keeping only the arguments that CHANGE:
 * `new self($this->a, $b, $this->c)` becomes `clone($this, ['b' => $b])`.
 */
final class HandRolledWitherScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->toCloneWith($match))
            ->rewrites();
    }

    private function toCloneWith(NodeMatch $match): ?string
    {
        $new = $match->node instanceof New_ ? $match->node : null;
        $params = AstNode::constructorParamsOf($match->enclosingClass());

        if ($new === null || $params === []) {
            return null;
        }

        $entries = $this->changedEntries($new, $params, $match->file->source);

        if ($entries === null) {
            return null;
        }

        return 'clone($this, [' . implode(', ', $entries) . '])';
    }

    /**
     * One `'prop' => <expr>` per argument that is NOT carried across verbatim. Null when any changed
     * argument's property can't be named for certain — a rewrite that guesses a key is worse than none.
     *
     * @param  list<\PhpParser\Node\Param>  $params
     * @return list<string>|null
     */
    private function changedEntries(New_ $new, array $params, string $source): ?array
    {
        $entries = [];

        foreach ($new->args as $index => $arg) {
            if (new AstNode($arg->value)->readsOwnProperty()) {
                continue;
            }

            $key = $arg->name?->toString() ?? AstNode::promotedParamName($params, $index);

            if ($key === null) {
                return null;
            }

            $value = Span::slice($source, $arg->value->getStartFilePos(), $arg->value->getEndFilePos());
            $entries[] = "'{$key}' => {$value}";
        }

        return $entries === [] ? null : $entries;
    }

}
