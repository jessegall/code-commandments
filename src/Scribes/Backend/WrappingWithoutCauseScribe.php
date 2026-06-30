<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\Catch_;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\WrappingWithoutCauseDetector}:
 * a `throw new X(...)` inside a `catch` that drops the caught exception. Pass it on as the
 * `previous:` cause so the stack trace survives — the exception-chaining the skill teaches.
 */
final class WrappingWithoutCauseScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->chain($match))
            ->rewrites();
    }

    private function chain(NodeMatch $match): ?string
    {
        $new = $match->node;

        if (! $new instanceof New_) {
            return null;
        }

        $caught = $this->caughtVariable($new);

        if ($caught === null) {
            return null;
        }

        // Insert the cause as a named argument before the closing `)` — appended to any
        // existing arguments (a named arg after positionals is valid).
        $text = $match->span()->text();
        $cause = ($new->args === [] ? '' : ', ') . "previous: \${$caught}";

        return substr($text, 0, -1) . $cause . ')';
    }

    /**
     * The name of the exception variable the enclosing `catch` binds. The detector only
     * flags a `new` under a variable-binding catch, so this is present.
     */
    private function caughtVariable(New_ $new): ?string
    {
        $node = $new->getAttribute('parent');

        while ($node instanceof Node) {
            if ($node instanceof Catch_ && $node->var !== null && is_string($node->var->name)) {
                return $node->var->name;
            }

            $node = $node->getAttribute('parent');
        }

        return null;
    }
}
