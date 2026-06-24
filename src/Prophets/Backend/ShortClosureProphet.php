<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Flag a big anonymous function. A multi-statement closure is a named method
 * that lost its name: it can't be unit-tested, can't be named, and buries the
 * intent of the method it sits in. Extract it to a private method.
 *
 *
 *
 * @method-generated-start
 * @method static maxClosureStatements(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.119.0')]
class ShortClosureProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Keep anonymous functions short — extract a big closure to a named private method';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A closure body runs more than a few statements (branching, loops, accumulation) — it is a named method in disguise that cannot be named or tested and hides the host method\'s intent.')
            ->leaveWhen('the closure is genuinely a small inline callback, or the body is one statement formatted across several lines (a single `new X(...)` with named args is fine — statements, not lines, are counted).')
            ->whenUnsure('if you can give the closure a name that reads — `candidatesFor(...)`, `serialiseDescriptor(...)` — extract it to a private method and pass `$this->name(...)`; if it is a trivial accumulator/mapper, leave it.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A closure is for a SHORT inline callback. Once its body grows branches, loops
and locals, it is a method that was denied a name: you cannot unit-test it,
cannot call it from elsewhere, and the method it lives in no longer reads as a
skeleton. Worse, a fat closure HIDES other smells — dispatch chains, feature
envy, long bodies — from the prophets that scan method bodies. Extract it.

Bad — a method's intent buried in a big map closure:
    return $this->registry->descriptorFor($id)
        ->map(function (NodeDescriptor $d) use ($socket, $workflow): array {
            $output = $d->findOutput($socket);
            if ($output->isEmpty()) { /* … */ }
            $candidates = [];
            foreach ($this->candidates($workflow) as $c) {
                if ($c->isTrigger()) { continue; }
                // …
            }
            return $candidates;
        })
        ->getOr([]);

Good — name it; the host method becomes a skeleton, the work is testable:
    return $this->registry->descriptorFor($id)
        ->map(fn (NodeDescriptor $d) => $this->candidatesFor($d, $socket, $workflow))
        ->getOr([]);

    private function candidatesFor(NodeDescriptor $d, string $socket, ?Workflow $w): array { /* … */ }

WHAT FIRES — a `function () { … }` closure whose body exceeds `max_closure_statements`
(default 5) STATEMENTS, counted recursively but NOT descending into nested
closures (each is judged on its own). Counting statements — not lines — means a
single multi-line `new X(...)` does not trip it.

WHAT DOES NOT — arrow functions (`fn () => …`, one expression by definition); a
small callback at or under the budget. The sibling of LongMethod, which only
measures NAMED methods and misses closures entirely.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $max = $this->maxStatements();
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\Closure::class) as $closure) {
            $count = $this->statementCount($closure->stmts);

            if ($count <= $max) {
                continue;
            }

            $line = $closure->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                sprintf(
                    'This closure runs %d statements (budget %d) — it is a named method in disguise. Extract it to a private method and pass `$this->name(...)`.',
                    $count,
                    $max,
                ),
                $this->lineSnippet($content, $line),
                'short-closure:' . $line,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Count statement nodes in a closure body, recursing through control-flow
     * but NOT descending into nested closures / arrow functions (each is judged
     * as its own unit).
     *
     * @param  array<Node\Stmt>  $stmts
     */
    private function statementCount(array $stmts): int
    {
        $counter = new class extends NodeVisitorAbstract {
            public int $count = 0;

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Node\Stmt) {
                    $this->count++;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($counter);
        $traverser->traverse($stmts);

        return $counter->count;
    }

    private function maxStatements(): int
    {
        $max = $this->config('max_closure_statements', 5);

        return is_int($max) && $max >= 1 ? $max : 5;
    }

}
