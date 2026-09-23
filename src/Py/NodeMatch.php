<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;
use JesseGall\CodeCommandments\Py\Node\AnnAssign;
use JesseGall\CodeCommandments\Py\Node\Block;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\ForLoop;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Py\Node\WhileLoop;
use JesseGall\CodeCommandments\ReadsFunctionBody;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\PhpTypes\Option;

/**
 * A Python node a query selected, together with the module it sits in — so it knows its `file:line`.
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
     * The name this node declares — a function's, a class's, a parameter's. Empty for a statement.
     */
    public function name(): string
    {
        return $this->node->declaredNames()[0] ?? '';
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
     * What the node IS, named where it has a name — `FunctionDef load`, `ClassDef Store`.
     */
    public function scope(): string
    {
        $kind = ClassName::short($this->node::class);

        return $this->name() === '' ? $kind : "{$kind} {$this->name()}";
    }

    /**
     * Is this a `def` written directly in a class body?
     */
    public function isMethod(): bool
    {
        return $this->node instanceof FunctionDef && $this->module->isMethod($this->node);
    }

    /**
     * How many choices this node sits inside within its own function or class body — each block an `if`,
     * a loop or a `match` owns is one level, so an `elif` adds none and a `try` or a `with` never counts.
     */
    public function branchingDepth(): int
    {
        $depth = 0;
        $child = $this->node;

        foreach ($this->enclosing() as $parent) {
            if ($child instanceof Block && $parent->isBranchingConstruct()) {
                $depth++;
            }

            $child = $parent;
        }

        return $depth;
    }

    /**
     * Is this an `if` whose branch already left — it ends in a `return`, `raise`, `continue` or `break` —
     * yet carries an `else:`? The `else` says nothing the exit did not, and indents the rest for it. An
     * `if` with `elif` rungs is a chain, not a guard, and is left to the ladder rule.
     */
    public function hasRedundantElse(): bool
    {
        if (! $this->node instanceof IfStmt || ! $this->node->else instanceof Block || $this->isElif()) {
            return false;
        }

        $body = $this->node->body->body;

        return $body !== [] && end($body)->isBailOut();
    }

    /**
     * How many rungs this `if` chain has when every one tests the SAME subject for equality with a
     * constant — `if kind == "box": … elif kind == "pallet": …` — the subjects compared as parsed
     * expressions. Zero for a chain whose rungs test anything else, and for an `elif` itself.
     */
    public function subjectLadderLength(): int
    {
        if (! $this->node instanceof IfStmt || $this->isElif()) {
            return 0;
        }

        $chain = $this->node->chain();
        $subjects = [];

        foreach ($chain as $rung) {
            $subject = $rung->test->comparisonSubject();

            if ($subject->is(ExprKind::Unknown)) {
                return 0;
            }

            $subjects[StructuralHash::ofExpression($subject)] = true;
        }

        return count($subjects) === 1 ? count($chain) : 0;
    }

    /**
     * Is this `if` — no `else`, no `elif` — the whole body of a loop, burying real work (two statements
     * or more) a level deep behind its condition? Named as the backend names it: the loop wants
     * `if not …: continue`. A one-line filter stays as it is, and so does a search — a body that ends by
     * leaving the loop picks the one item it wanted.
     */
    public function isSoleLoopBodyGuard(): bool
    {
        if (! $this->node instanceof IfStmt || $this->node->else !== null || count($this->node->body->body) < 2) {
            return false;
        }

        $work = $this->node->body->body;

        if (end($work)->isBailOut()) {
            return false;
        }

        [$block, $loop] = [...$this->module->ancestorsOf($this->node), null, null];

        return ($loop instanceof ForLoop || $loop instanceof WhileLoop) && $loop->body === $block && count($block->body) === 1;
    }

    /**
     * Does this function take a callable defaulted to `None` — `cb: Callable | None = None` — and then
     * ask in its own body whether it was given one, where a no-op default would let it just call?
     */
    public function hasNullNormalisedOptionalCallback(): bool
    {
        return $this->node instanceof FunctionDef && array_any($this->node->params, fn (Param $param): bool => $param->annotation?->isOptionalCallableType() === true
            && $param->default?->literalType() === LiteralType::None
            && $this->module->asksAbsenceOf($this->node, $param->name));
    }

    /**
     * Is this a `str` declaration defaulting to the blank — a parameter `x: str = ''`, or a class field
     * `x: str = ''` — a total type whose default says "nothing"?
     */
    public function isBlankStringDefault(): bool
    {
        return $this->blankDefault()->isSome();
    }

    /**
     * Does the scope of this blank-defaulted declaration ask whether it is blank — the question that
     * proves the blank is absence wearing a total type? A parameter is asked in its function, a field
     * as `self.x` in its class.
     */
    public function defaultedNameTestedForBlankness(): bool
    {
        return $this->blankDefault()->isSomeAnd(fn (array $declared): bool => $this->module->asksBlanknessOf(...$declared));
    }

    /**
     * The scope a blank-defaulted `str` declaration is read in, and the name it is read by there.
     *
     * @return Option<array{Node, string}>
     */
    private function blankDefault(): Option
    {
        $node = $this->node;
        $ancestors = $this->module->ancestorsOf($node);

        if ($node instanceof Param && $node->annotation?->dottedName() === 'str' && $node->default?->isBlankString() === true) {
            return Option::some([$ancestors[0], $node->name]);
        }

        $inClass = ($ancestors[1] ?? null) instanceof ClassDef;
        $field = $node instanceof AnnAssign && $inClass && $node->target->is(ExprKind::Name);

        return $field && $node->annotation->dottedName() === 'str' && $node->value?->isBlankString() === true
            ? Option::some([$ancestors[1], 'self.' . $node->target->get('name')])
            : Option::none();
    }

    /**
     * Is this an `elif` — an `if` standing as another `if`'s else, one rung of its chain?
     */
    public function isElif(): bool
    {
        return $this->node instanceof IfStmt && $this->module->parentOf($this->node)->isSomeAnd(static fn (Node $parent): bool => $parent instanceof IfStmt);
    }

    /**
     * What this node belongs to — the class a method is declared in, or its module for anything else —
     * named by where it is, so two of them compare.
     */
    public function owner(): string
    {
        $class = array_values(array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node instanceof ClassDef))[0] ?? null;

        return $class instanceof ClassDef ? "{$this->module->file}::{$class->name}" : $this->module->file;
    }

    /**
     * Does this method override one a base class in the codebase declares?
     */
    public function isOverride(Codebase $codebase): bool
    {
        return $this->node instanceof FunctionDef && $codebase->index()->isOverride($this->node, $this->module);
    }

    /**
     * The `def` this node is written in — none at a module's or a class's top level.
     *
     * @return Option<FunctionDef>
     */
    public function enclosingFunction(): Option
    {
        $around = array_filter($this->module->ancestorsOf($this->node), static fn (Node $node): bool => $node instanceof FunctionDef);

        return Option::fromNullable(array_values($around)[0] ?? null);
    }

    /**
     * Is this a class's `__init__` — structure every class declares for itself, which two classes cannot
     * share however alike they read?
     */
    public function isConstructorDeclaration(): bool
    {
        return $this->node instanceof FunctionDef && $this->node->name === '__init__' && $this->isMethod();
    }

    /**
     * Is the function body only placeholders — an abstract or protocol method, an overload, a hook left
     * for subclasses? Every stub reads alike, and none holds logic to share.
     */
    public function isStub(): bool
    {
        return $this->node->functionBody()->isSomeAnd(
            static fn (Block $body): bool => array_all($body->children(), static fn (Node $statement): bool => $statement->isPlaceholder()),
        );
    }

    protected static function syntaxHash(): string
    {
        return StructuralHash::class;
    }

    /**
     * The nodes around this one, innermost first, up to the `def` or `class` it belongs to.
     *
     * @return list<Node>
     */
    private function enclosing(): array
    {
        $enclosing = [];

        foreach ($this->module->ancestorsOf($this->node) as $ancestor) {
            if ($ancestor instanceof FunctionDef || $ancestor instanceof ClassDef) {
                break;
            }

            $enclosing[] = $ancestor;
        }

        return $enclosing;
    }
}
