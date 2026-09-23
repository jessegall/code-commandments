<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\ReadsFunctionBody;
use JesseGall\CodeCommandments\Span;
use JesseGall\PhpTypes\Option;

/**
 * A C# node a query found, with the file it is in — a statement, a member or an expression alike.
 * Non-final by design: subclass it to hang domain predicates a `where` closure can type-hint.
 */
class NodeMatch implements Located
{
    use ReadsFunctionBody;

    public function __construct(
        public readonly Node $node,
        public readonly ModuleFile $module,
    ) {}

    /**
     * The name the node declares or reads — a method's, a class's, an identifier's; an accessor answers
     * for the property, indexer or event it belongs to, as the compiler names it (`get_Total`). Empty for
     * anything nameless.
     */
    public function name(): string
    {
        if ($this->node->name !== null || ! str_ends_with($this->node->kind, 'AccessorDeclaration')) {
            return $this->node->name ?? '';
        }

        foreach ($this->module->ancestorsOf($this->node) as $member) {
            if ($member->is('PropertyDeclaration', 'IndexerDeclaration', 'EventDeclaration') && $member->name !== null) {
                return $member->name;
            }
        }

        return '';
    }

    public function line(): int
    {
        return $this->module->lineAt($this->node->start);
    }

    public function file(): string
    {
        return $this->module->file;
    }

    public function location(): string
    {
        return $this->file() . ':' . $this->line();
    }

    public function span(): Span
    {
        return $this->module->spanAt($this->node->start, $this->node->end);
    }

    /**
     * What the node IS, named where it has a name — `MethodDeclaration Total`, `ClassDeclaration Order`.
     */
    public function scope(): string
    {
        return $this->name() === '' ? $this->node->kind : "{$this->node->kind} {$this->name()}";
    }

    public function isConstructorDeclaration(): bool
    {
        return $this->node->is('ConstructorDeclaration');
    }

    /**
     * A body that only says it is not written yet — `throw new NotImplementedException()`, as a block or
     * an expression body. (A member with no body at all — abstract, on an interface, a partial's
     * declaring half — is no function to begin with.)
     */
    public function isStub(): bool
    {
        return $this->node->functionBody()->isSomeAnd(
            static fn (Node $body): bool => self::thrown($body)->isSomeAnd(static fn (Node $exception): bool => $exception->type?->name === 'global::System.NotImplementedException'),
        );
    }

    /**
     * Does this member override a base member or implement an interface's, as the compiler resolved it?
     * Its shape is the contract's before it is its own.
     */
    public function isOverride(): bool
    {
        return $this->node->inherited;
    }

    /**
     * What $body throws when throwing is all it does — `{ throw …; }` or `=> throw …`.
     *
     * @return Option<Node>
     */
    private static function thrown(Node $body): Option
    {
        $throw = match (true) {
            $body->is('Block') && count($body->children()) === 1 && $body->children()[0]->is('ThrowStatement') => $body->children()[0],
            $body->is('ArrowExpressionClause') && $body->expressions()[0]->is('ThrowExpression') => $body->expressions()[0],
            default => null,
        };

        return Option::fromNullable($throw?->expressions()[0] ?? null);
    }

    /**
     * Is this an `else if` — an `if` standing as another `if`'s `else`, one rung of its ladder?
     */
    public function isElseIf(): bool
    {
        return $this->node->is('IfStatement') && $this->module->parentOf($this->node)->isSomeAnd(static fn (Node $parent): bool => $parent->is('ElseClause'));
    }

    /**
     * Is this an `if` whose branch already left — it ends in a `return`, `throw`, `continue`, `break` or
     * `yield break` — yet carries an `else`? The `else` says nothing the exit did not, and indents the
     * rest for it. An `if` whose `else` is the next rung is a ladder, not a guard, and is left to the
     * ladder rule.
     */
    public function hasRedundantElse(): bool
    {
        if (! $this->node->is('IfStatement') || $this->isElseIf()) {
            return false;
        }

        [$branch, $else] = [$this->node->children()[0], $this->node->children()[1] ?? null];

        if ($else === null || $else->children()[0]->is('IfStatement')) {
            return false;
        }

        $statements = $branch->is('Block') ? $branch->children() : [$branch];

        return $statements !== [] && end($statements)->isBailOut();
    }

    /**
     * How many rungs this `if` ladder has when every one compares the SAME subject with a constant —
     * `if (kind == Kind.Box) … else if (kind == Kind.Pallet) …` — the subjects compared as fingerprinted
     * expressions. Zero for a ladder whose rungs test anything else, and for an `else if` itself.
     */
    public function subjectLadderLength(): int
    {
        if (! $this->node->is('IfStatement') || $this->isElseIf()) {
            return 0;
        }

        $subjects = [];

        foreach (self::rungs($this->node) as $rung) {
            $subject = $rung->expressions()[0]->comparisonSubject();

            if ($subject->isNone()) {
                return 0;
            }

            $subjects[StructuralHash::ofExpression($subject->unwrap())] = true;
        }

        return count($subjects) === 1 ? count(self::rungs($this->node)) : 0;
    }

    /**
     * The `if` statements of the ladder $if opens — itself, then each `else if` in turn.
     *
     * @return list<Node>
     */
    private static function rungs(Node $if): array
    {
        $else = $if->children()[1] ?? null;
        $next = $else?->children()[0];

        return [$if, ...($next?->is('IfStatement') === true ? self::rungs($next) : [])];
    }

    /**
     * How many choices this node sits inside, within the function it belongs to: each `if`, loop or
     * `switch` whose body holds it — an `else if` a rung of the ladder it continues, not a level of its
     * own.
     */
    public function branchingDepth(): int
    {
        $depth = 0;
        $child = $this->node;

        foreach ($this->module->ancestorsOf($this->node) as $parent) {
            if ($parent->isFunction()) {
                break;
            }

            if ($parent->isBranchingConstruct() && ! self::continuesTheLadder($child)) {
                $depth++;
            }

            $child = $parent;
        }

        return $depth;
    }

    /**
     * Is $child the `else` of an `if` that holds only the next `if` — a rung, not a body?
     */
    private static function continuesTheLadder(Node $child): bool
    {
        return $child->is('ElseClause') && ($child->children()[0] ?? null)?->is('IfStatement') === true;
    }

    protected static function syntaxHash(): string
    {
        return StructuralHash::class;
    }
}
