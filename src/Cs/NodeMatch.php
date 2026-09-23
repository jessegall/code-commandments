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
     * Is this `if` — no `else` — the whole body of a loop, burying real work (two statements or more) a
     * level deep behind its condition? The loop wants `if (!…) continue;`. A one-statement filter stays
     * as it is, and so does a search — a body that ends by leaving the loop picks the one item it wanted.
     */
    public function isSoleLoopBodyGuard(): bool
    {
        if (! $this->node->is('IfStatement') || count($this->node->children()) !== 1) {
            return false;
        }

        $branch = $this->node->children()[0];
        $work = $branch->is('Block') ? $branch->children() : [$branch];

        if (count($work) < 2 || end($work)->isBailOut()) {
            return false;
        }

        $ancestors = $this->module->ancestorsOf($this->node);
        [$holder, $loop] = ($ancestors[0] ?? null)?->is('Block') === true ? [$ancestors[0], $ancestors[1] ?? null] : [null, $ancestors[0] ?? null];

        return $loop?->isLoop() === true && ($holder === null || count($holder->children()) === 1);
    }

    /**
     * Is this expression handed straight to a call as one of its arguments?
     */
    public function fillsArgument(): bool
    {
        return $this->module->parentOf($this->node)->isSomeAnd(static fn (Node $parent): bool => $parent->is('Argument'));
    }

    /**
     * Does this member answer a lookup miss with an invented empty value? Every value it returns — each
     * arm of a conditional counted on its own — is either `""`/`0`/`false`, or what a dictionary lookup
     * found: the lookup itself, or the `out` variable a `TryGetValue` in this member filled.
     */
    public function isInventingOnMiss(): bool
    {
        $body = $this->node->functionBody();

        if ($body->isNone()) {
            return false;
        }

        $scope = [$body->unwrap(), ...$body->unwrap()->descendants()];
        $answers = array_merge([], ...array_map(
            static fn (Node $return): array => $return->returnedValue()->mapOr([], static fn (Node $value): array => $value->answers()),
            array_values(array_filter($scope, static fn (Node $node): bool => $node->isReturn())),
        ));
        $found = self::lookedUpNames($scope);
        $invented = array_filter($answers, static fn (Node $answer): bool => $answer->isEmptyScalar());
        $real = array_filter($answers, static fn (Node $answer): bool => ! $answer->isEmptyScalar());

        return $invented !== [] && $real !== [] && array_all($real, static fn (Node $answer): bool => $answer->isLookup() || ($answer->is('IdentifierName') && in_array($answer->name, $found, true)));
    }

    /**
     * The names of the `out` variables a `TryGetValue` among $scope fills.
     *
     * @param  list<Node>  $scope
     * @return list<string>
     */
    private static function lookedUpNames(array $scope): array
    {
        $names = [];

        foreach ($scope as $node) {
            foreach ($node->expressions() as $expression) {
                foreach (array_filter($expression->flatten(), static fn (Node $call): bool => $call->isLookup() && $call->isCall()) as $lookup) {
                    foreach ($lookup->flatten() as $part) {
                        if ($part->is('DeclarationExpression')) {
                            $names = [...$names, ...array_filter(array_map(static fn (Node $designation): ?string => $designation->name, array_filter($part->children, static fn (Node $child): bool => $child->is('SingleVariableDesignation'))))];
                        }
                    }
                }
            }
        }

        return $names;
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
