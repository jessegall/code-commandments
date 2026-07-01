<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\Calls;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;

/**
 * A matched node with its file — an {@see AstNode} that also knows where it is.
 * The result a query returns and a finding is reported as.
 *
 * NOT final on purpose: a project can SUBCLASS it (adding domain predicates like
 * `isVehicleClause()`) and TYPE-HINT that subclass in a `where` closure — the query reflects the
 * closure's parameter and hands it that node ({@see Query::where}), no registration needed, so its
 * own detectors read as cleanly as the built-ins.
 */
class NodeMatch extends AstNode
{
    public function __construct(
        Node $node,
        public readonly ParsedFile $file,
        public readonly ?Codebase $codebase = null,
    ) {
        parent::__construct($node);
    }

    /**
     * The 1-based line where this match begins.
     */
    public function line(): int
    {
        return $this->node->getStartLine();
    }

    /**
     * The match's `path:line`, the form a finding is reported as.
     */
    public function location(): string
    {
        return "{$this->file->path}:{$this->line()}";
    }

    /**
     * This match's position AS a {@see Span} — the seam the scribe layer rewrites
     * through, the backend mirror of {@see \JesseGall\CodeCommandments\Vue\ElementMatch::span()}.
     * php-parser end offsets are INCLUSIVE; a {@see Span} end is EXCLUSIVE, hence `+ 1`.
     */
    public function span(): Span
    {
        return new Span(
            $this->file->path,
            $this->file->source,
            $this->node->getStartFilePos(),
            $this->node->getEndFilePos() + 1,
        );
    }

    /**
     * Trace this variable through its enclosing function: every place it travels
     * to, in source order, each classified as an {@see Interaction}. Returns an
     * empty trace when this match is not a (named) variable.
     *
     * @return list<Interaction>
     */
    public function trace(): array
    {
        if (! $this->node instanceof Variable || ! is_string($this->node->name)) {
            return [];
        }

        $function = $this->enclosingFunction();

        if ($function === null) {
            return [];
        }

        $interactions = [];

        foreach ((new NodeFinder)->findInstanceOf([$function], Variable::class) as $occurrence) {
            if ($occurrence->name === $this->node->name) {
                $match = new self($occurrence, $this->file, $this->codebase);
                $interactions[] = new Interaction($match, $match->interactionKind());
            }
        }

        return $interactions;
    }

    /**
     * Is this expression's result de-nulled — directly ({@see isDeNulled}), or via
     * the variable it's assigned to anywhere in the function? The assigned-variable
     * case is answered by tracing that variable: if any stop on its journey is a
     * null guard, the result is being checked for absence downstream.
     */
    public function resultIsDeNulled(): bool
    {
        if ($this->isDeNulled()) {
            return true;
        }

        $parent = $this->parent()->node;

        if (! $parent instanceof Assign || ! $parent->var instanceof Variable) {
            return false;
        }

        foreach (new self($parent->var, $this->file, $this->codebase)->trace() as $interaction) {
            if ($interaction->deNulls()) {
                return true;
            }
        }

        return false;
    }

    /**
     * For a method call `$x->m()`: is the receiver variable `$x` mutated by a
     * property write (`$x->prop = …`) elsewhere in the same function? Found by
     * tracing the receiver and looking for a {@see InteractionKind::PropertyWrite}.
     *
     * `$this` is excluded: a model writing its own fields then `$this->save()` is
     * the intention method itself — the right home, not a call-site mutation.
     */
    public function receiverMutatedNearby(): bool
    {
        $receiver = $this->node->var ?? null;

        if (! $receiver instanceof Variable || $receiver->name === 'this') {
            return false;
        }

        foreach (new self($receiver, $this->file, $this->codebase)->trace() as $interaction) {
            if ($interaction->kind === InteractionKind::PropertyWrite) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is there a call to $name within $lines of this match, in the same file?
     */
    public function near(string $name, int $lines = 5): bool
    {
        $line = $this->line();

        foreach ((new NodeFinder)->find($this->file->ast, static fn (Node $n): bool => Calls::name($n) === $name) as $other) {
            if ($other !== $this->node && abs($other->getStartLine() - $line) <= $lines) {
                return true;
            }
        }

        return false;
    }
}
