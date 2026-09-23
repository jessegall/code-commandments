<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;
use JesseGall\CodeCommandments\Py\Node\AnnAssign;
use JesseGall\CodeCommandments\Py\Node\Assign;
use JesseGall\CodeCommandments\Py\Node\Block;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\ExceptHandler;
use JesseGall\CodeCommandments\Py\Node\ExprStmt;
use JesseGall\CodeCommandments\Py\Node\ForLoop;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\MatchStmt;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Py\Node\Raise;
use JesseGall\CodeCommandments\Py\Node\Simple;
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
     * Is this a `match` on `x.value` whose cases are literals — every one a value of one enum the
     * codebase declares — outside that enum? The raw values re-state the enum at a call site; the
     * mapping belongs on the enum, matching its members.
     */
    public function isMatchOnEnumValue(Enums $enums): bool
    {
        if (! $this->node instanceof MatchStmt) {
            return false;
        }

        $subject = $this->node->subject;

        return $subject->is(ExprKind::Attribute) && $subject->get('name') === 'value' && $subject->rootName() !== 'self'
            && $enums->holdAll($this->node->literalCaseKeys());
    }

    /**
     * Is this a `match` on anything but `x.value` whose cases are strings — every one a value of one
     * enum the codebase declares? The loose strings stand in for the type that already seals them.
     */
    public function isStringMatchMirroringEnum(Enums $enums): bool
    {
        if (! $this->node instanceof MatchStmt) {
            return false;
        }

        $subject = $this->node->subject;
        $readsValue = $subject->is(ExprKind::Attribute) && $subject->get('name') === 'value';

        return ! $readsValue && $enums->holdAll($this->node->textCaseKeys());
    }

    /**
     * Is this a `match` over one declared enum's members whose `case _:` answers with nothing — `None`,
     * `False`, an empty value — while every member it handles gets a real answer? A member added later
     * falls into the wildcard and comes back as nothing, silently.
     */
    public function isEnumMatchWithAbsentWildcard(Enums $enums): bool
    {
        if (! $this->node instanceof MatchStmt) {
            return false;
        }

        $classes = $this->node->memberCaseClasses();

        return count($classes) === 1 && $enums->isEnum($classes[0]) && $this->node->onlyTheWildcardAnswersAbsence();
    }

    /**
     * Does this method save one of its own attributes to a local and later restore it from that local
     * — `previous = self.scope … self.scope = previous`? The dance only makes sense for per-call scratch
     * state kept on the object; the value is really an input. The local must hold what it saved — one
     * reassigned in between is a read-modify-write, and a parameter already held a value — and a `@contextmanager` is exempt: its declared job
     * is a change it undoes.
     */
    public function hasOwnStateSaveAndRestore(): bool
    {
        if (! $this->node instanceof FunctionDef || $this->node->isContextManager()) {
            return false;
        }

        $writes = array_filter($this->module->nodes(), fn (Node $node): bool => $node->writtenTargets() !== [] && in_array($this->node, $this->module->ancestorsOf($node), true));
        $locals = array_count_values(array_merge([], ...array_map(
            static fn (Node $write): array => array_map(static fn (Expr $target): string => $target->dottedName(), $write->writtenTargets()),
            array_values($writes),
        )));
        $parameters = array_map(static fn (Param $param): string => $param->name, $this->node->params);
        $saved = [];

        foreach (array_filter($writes, static fn (Node $write): bool => $write instanceof Assign) as $assign) {
            $local = $assign->targets[0]->is(ExprKind::Name) ? (string) $assign->targets[0]->get('name') : '';

            if ($local !== '' && self::isOwnState($assign->value) && $locals[$local] === 1 && ! in_array($local, $parameters, true)) {
                $saved[$local] = $assign->value->dottedName();
            }
        }

        return array_any($writes, static fn (Node $write): bool => $write instanceof Assign && $write->value->is(ExprKind::Name)
            && ($saved[(string) $write->value->get('name')] ?? null) === $write->targets[0]->dottedName());
    }

    /**
     * Is $expression a read of the object's own state — `self.scope`, `self.ctx.user`?
     */
    private static function isOwnState(Expr $expression): bool
    {
        return $expression->is(ExprKind::Attribute) && $expression->rootName() === 'self' && $expression->dottedName() !== '';
    }

    /**
     * Is this a dataclass whose own methods write one of the fields it was built from — `self.amount
     * += …`, or `object.__setattr__(self, 'amount', …)` past `frozen` — after construction? Equality,
     * sharing and caching all rest on the value staying what it is.
     */
    public function isValueWrittenAfterConstruction(): bool
    {
        if (! $this->node instanceof ClassDef || ! $this->node->isDataclass()) {
            return false;
        }

        $fields = $this->node->initFieldNames();

        return array_any($this->node->methodsAfterConstruction(), fn (FunctionDef $method): bool => array_any(
            $this->module->expressionsIn($method),
            static fn (Expr $expression): bool => $expression->setsOwnAttribute($fields),
        ) || $this->assignsOwnField($method, $fields));
    }

    /**
     * Does $method assign `self.x` for one of $fields — other than filling it once as a memo?
     *
     * @param  list<string>  $fields
     */
    private function assignsOwnField(FunctionDef $method, array $fields): bool
    {
        $writes = array_filter($this->module->nodes(), fn (Node $node): bool => in_array($method, $this->module->ancestorsOf($node), true));

        return array_any($writes, fn (Node $write): bool => array_any($write->writtenTargets(), fn (Expr $target): bool => $target->isOwnAttributeRead()
            && in_array($target->get('name'), $fields, true)
            && ! $this->isMemoFill($write, $target->dottedName())));
    }

    /**
     * Is this a class that is nothing but scalar constants — `PENDING = 'pending'`, `PAID = 'paid'` — a
     * closed set of values written out by hand instead of an `Enum`? A class with a base other than
     * `object` or a decorator is something else already, and has no enum to become.
     */
    public function isScalarConstantClass(): bool
    {
        if (! $this->node instanceof ClassDef || $this->node->decorators !== [] || ! array_all($this->node->bases, static fn (Expr $base): bool => $base->dottedName() === 'object')) {
            return false;
        }

        $statements = array_filter($this->node->body->body, static fn (Node $statement): bool => ! ($statement instanceof ExprStmt && $statement->isBareString()));

        return count($statements) >= 2 && array_all($statements, static fn (Node $statement): bool => $statement instanceof Assign
            && count($statement->targets) === 1
            && $statement->targets[0]->is(ExprKind::Name)
            && $statement->value->isScalarValue());
    }

    /**
     * Is this a write to state no instance owns — a name its function declared `global`, or an
     * attribute of the class itself set from a method, through `cls`, the class's own name or
     * `type(self)`? Whoever writes last wins, and nothing in any signature says who does.
     */
    public function isStaticStateWrite(): bool
    {
        return $this->enclosingFunction()->isSomeAnd(fn (FunctionDef $function): bool => array_any(
            $this->node->writtenTargets(),
            fn (Expr $target) => $this->writesStatic($target, $function),
        ));
    }

    /**
     * Does assigning $target inside $function write static state?
     */
    private function writesStatic(Expr $target, FunctionDef $function): bool
    {
        if ($target->is(ExprKind::Name)) {
            return in_array($target->get('name'), $this->globalsOf($function), true) && ! $this->isMemoFill($this->node, $target->get('name'));
        }

        if (! $target->is(ExprKind::Attribute)) {
            return false;
        }

        $owner = $target->get('object');

        return ($owner->isCall() && $owner->get('callee')->dottedName() === 'type')
            || ($owner->is(ExprKind::Name) && in_array($owner->get('name'), $this->classNamesFor($function), true));
    }

    /**
     * Is this write the one-time fill of a memo — inside `if x is None:`, `if not x:` or
     * `if len(x) == 0:` asking about the very name it writes? It adds nothing a caller can observe but speed.
     */
    private function isMemoFill(Node $write, string $name): bool
    {
        [$block, $if] = [...$this->module->ancestorsOf($write), null, null];

        return $if instanceof IfStmt && $if->body === $block && ($if->test->testsNoneOf($name) || $if->test->testsBlanknessOf($name) || $if->test->testsEmptinessOf($name));
    }

    /**
     * The names $function declares `global`.
     *
     * @return list<string>
     */
    private function globalsOf(FunctionDef $function): array
    {
        $declarations = array_filter($this->module->nodes(), fn (Node $node): bool => $node instanceof Simple
            && $this->functionHolding($node)->isSomeAnd(static fn (FunctionDef $holder): bool => $holder === $function));

        return array_merge([], ...array_map(static fn (Simple $declaration): array => $declaration->globalNames(), array_values($declarations)));
    }

    /**
     * The `def` $node is written in, innermost — none at a module's or a class's top level.
     *
     * @return Option<FunctionDef>
     */
    private function functionHolding(Node $node): Option
    {
        $around = array_filter($this->module->ancestorsOf($node), static fn (Node $ancestor): bool => $ancestor instanceof FunctionDef);

        return Option::fromNullable(array_values($around)[0] ?? null);
    }

    /**
     * The names a method reaches its own class by — the class's name, and the first parameter of a
     * `@classmethod`. None for a function outside a class.
     *
     * @return list<string>
     */
    private function classNamesFor(FunctionDef $function): array
    {
        if (! $this->module->isMethod($function)) {
            return [];
        }

        $class = array_values(array_filter($this->module->ancestorsOf($function), static fn (Node $around): bool => $around instanceof ClassDef))[0];
        $bound = array_any($function->decorators, static fn (Expr $decorator): bool => $decorator->dottedName() === 'classmethod');

        return $bound && $function->params !== [] ? [$class->name, $function->params[0]->name] : [$class->name];
    }

    /**
     * Does this class's `__init__` tell a collaborator to act and throw the answer away — one it was
     * handed, or one it keeps in a field? Discarding the result is what shows the call was made for
     * what it DID. Asking a collaborator for something and keeping it, calling its own helpers,
     * filling its own fields, and a call tried to see whether it raises are assembly, not this.
     */
    public function constructorHasSideEffect(): bool
    {
        return $this->constructor()->isSomeAnd(fn (FunctionDef $init): bool => array_any(
            $this->module->expressionsIn($init),
            fn (Expr $expression): bool => $this->module->isDiscarded($expression)
                && ! $this->module->isProbed($expression)
                && $this->actsOnCollaborator($expression, $init),
        ));
    }

    /**
     * The `__init__` this class declares — none for a class that inherits its own.
     *
     * @return Option<FunctionDef>
     */
    private function constructor(): Option
    {
        $body = $this->node instanceof ClassDef ? $this->node->body->body : [];
        $declared = array_filter($body, static fn (Node $statement): bool => $statement instanceof FunctionDef && $statement->name === '__init__');

        return Option::fromNullable(array_values($declared)[0] ?? null);
    }

    /**
     * Is $call a method called on something $init was handed — the parameter itself, or a field
     * assigned from one? `*args` and `**options` are not handed in: each call builds them afresh.
     */
    private function actsOnCollaborator(Expr $call, FunctionDef $init): bool
    {
        if (! $call->isCall() || ! $call->get('callee')->is(ExprKind::Attribute)) {
            return false;
        }

        $receiver = $call->get('callee')->get('object');
        $collaborators = array_filter(array_slice($init->params, 1), static fn (Param $param): bool => $param->kind === '');
        $handed = array_values(array_map(static fn (Param $param): string => $param->name, $collaborators));
        $held = $receiver->reachedThrough();

        return in_array($receiver->rootName(), $handed, true) || ($held !== '' && in_array($held, $this->heldCollaborators($init, $handed), true));
    }

    /**
     * The `self.x` fields $init assigns only from what it was handed — a field it ever fills from
     * something else is its own.
     *
     * @param  list<string>  $handed
     * @return list<string>
     */
    private function heldCollaborators(FunctionDef $init, array $handed): array
    {
        $assigns = array_filter($this->module->nodes(), fn (Node $node): bool => $node instanceof Assign && in_array($init, $this->module->ancestorsOf($node), true));
        $sources = [];

        foreach ($assigns as $assign) {
            foreach ($assign->targets as $target) {
                $sources[$target->dottedName()][] = $assign->value->rootName();
            }
        }

        return array_keys(array_filter($sources, static fn (array $roots): bool => array_all($roots, static fn (string $root): bool => in_array($root, $handed, true))));
    }

    /**
     * Is this a `raise` of a new exception inside an `except` block with no `from` — the failure it
     * handles left as an implicit context instead of named as the cause? `raise` bare, `raise e` of the
     * caught one, and `from None` all say what they mean and are not this.
     */
    public function isRaiseWithoutCause(): bool
    {
        if (! $this->node instanceof Raise || $this->node->exception === null || $this->node->cause !== null) {
            return false;
        }

        $raised = $this->node->exception->dottedName();

        return $this->enclosingHandler()->isSomeAnd(fn (ExceptHandler $handler): bool => ($handler->name === null || $raised !== $handler->name)
            && ! $this->setsCauseOf($handler, $raised));
    }

    /**
     * Does $handler assign `$raised.__cause__` by hand — the chaining `from` spells, written longhand?
     */
    private function setsCauseOf(ExceptHandler $handler, string $raised): bool
    {
        $assigns = array_filter($this->module->nodes(), fn (Node $node): bool => $node instanceof Assign && in_array($handler, $this->module->ancestorsOf($node), true));

        return $raised !== '' && array_any($assigns, static fn (Assign $assign): bool => array_any(
            $assign->targets,
            static fn (Expr $target): bool => $target->dottedName() === "{$raised}.__cause__",
        ));
    }

    /**
     * The `except` block this node is written in, within its own function — none outside one, and none
     * across a `def` or a `class` written inside the handler.
     *
     * @return Option<ExceptHandler>
     */
    private function enclosingHandler(): Option
    {
        foreach ($this->module->ancestorsOf($this->node) as $around) {
            if ($around->isScope()) {
                return Option::none();
            }

            if ($around instanceof ExceptHandler) {
                return Option::some($around);
            }
        }

        return Option::none();
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
        return $this->functionHolding($this->node);
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
