<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\AnnAssign;
use JesseGall\CodeCommandments\Py\Node\Assign;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\ForLoop;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Span;
use JesseGall\PhpTypes\Option;

/**
 * A Python expression a query selected, together with its module — so it knows its `file:line`.
 * Non-final by design: subclass it to hang domain predicates a `where` closure can type-hint.
 */
class ExprMatch implements Located
{
    public function __construct(
        public readonly Expr $expr,
        public readonly ModuleFile $module,
    ) {}

    /**
     * What a call calls, as a dotted path — `os.listdir`, `self.rows().clear`. Empty for anything but a call.
     */
    public function callName(): string
    {
        return $this->expr->isCall() ? self::path($this->expr->get('callee')) : '';
    }

    public function line(): int
    {
        return $this->module->lineAt($this->expr->start);
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
        return $this->module->spanAt($this->expr->start, $this->expr->end);
    }

    public function scope(): string
    {
        return $this->expr->kind->value;
    }

    /**
     * $expr as the dotted path it reads — a name, an attribute chain, a call written `()` along the way.
     */
    private static function path(Expr $expr): string
    {
        return match ($expr->kind) {
            ExprKind::Name => $expr->get('name'),
            ExprKind::Attribute => self::path($expr->get('object')) . '.' . $expr->get('name'),
            ExprKind::Call => self::path($expr->get('callee')) . '()',
            ExprKind::Subscript => self::path($expr->get('object')) . '[]',
            default => $expr->kind->value,
        };
    }

    /**
     * Is this expression passed straight into a call — a positional argument, or a keyword's value?
     * Named as the backend names it.
     */
    public function fillsArgument(): bool
    {
        $wrapper = $this->module->wrapperOf($this->expr);

        if ($wrapper->isSomeAnd(static fn (Expr $keyword): bool => $keyword->is(ExprKind::Keyword))) {
            $wrapper = $this->module->wrapperOf($wrapper->unwrap());
        }

        return $wrapper->isSomeAnd(fn (Expr $call): bool => $call->isCall() && $call->get('callee') !== $this->expr);
    }

    /**
     * Is this a string-key read — `row["sku"]`, `row.get("sku")` — of a name the enclosing function
     * annotates as a dict?
     */
    public function isDictKeyRead(): bool
    {
        $base = $this->expr->stringKeyBase()->filter(static fn (Expr $read): bool => $read->is(ExprKind::Name));

        return $base->isSomeAnd(fn (Expr $name): bool => $this->module->functionOf($this->expr)->isSomeAnd(
            static fn (FunctionDef $function): bool => $function->annotationOf((string) $name->get('name'))->isSomeAnd(
                static fn (Expr $annotation): bool => $annotation->isDictType(),
            ),
        ));
    }

    /**
     * Is this written in a dunder — `__init__`, `__sub__` — whose protocol hands it a value of any type to
     * sort out?
     */
    public function isInDunder(): bool
    {
        return $this->module->functionOf($this->expr)->isSomeAnd(static fn (FunctionDef $function): bool => $function->isDunder());
    }

    /**
     * Is this written in a named constructor — a `@classmethod` returning `cls(...)`?
     */
    public function isWithinNamedConstructor(): bool
    {
        return $this->module->functionOf($this->expr)->isSomeAnd(static fn (FunctionDef $function): bool => $function->isNamedConstructor());
    }

    /**
     * Is this the collection a `for` statement walks — `rows` in `for row in rows:`?
     */
    public function isLoopSubject(): bool
    {
        return $this->module->ownerOf($this->expr)->isSomeAnd(fn (Node $owner): bool => $owner instanceof ForLoop && $owner->iterable === $this->expr);
    }

    /**
     * Does this fall back to an empty collection — `x or []`, `m.get(k, ())`?
     */
    public function fallsBackToEmptyCollection(): bool
    {
        return $this->expr->fallback()->isSomeAnd(static fn (Expr $fallback): bool => $fallback->isEmptyCollection());
    }

    /**
     * Is what this falls back FROM read off a parameter the caller handed in — `below` in
     * `below.get(id, [])`? A method's own `self` or `cls` is not handed in: reading its state is the
     * object answering for itself.
     */
    public function fallbackReachesIntoParameter(): bool
    {
        return $this->expr->fallbackSubject()->isSomeAnd(fn (Expr $subject): bool => $this->module->functionOf($this->expr)->isSomeAnd(
            fn (FunctionDef $function): bool => in_array($subject->rootName(), $this->handedIn($function), true),
        ));
    }

    /**
     * Is this the outermost of a conditional expression nested in another's branch — the one finding a
     * chain of them yields?
     */
    public function isOutermostNestedConditional(): bool
    {
        return $this->expr->nestsConditional() && ! $this->isInsideConditional();
    }

    /**
     * Does any expression this one sits in — out to its statement — read as a conditional?
     */
    private function isInsideConditional(): bool
    {
        return array_any($this->module->wrappersOf($this->expr), static fn (Expr $around): bool => $around->is(ExprKind::Conditional));
    }

    /**
     * Is this the whole of a statement — a value computed and thrown away?
     */
    public function resultIsDiscarded(): bool
    {
        return $this->module->isDiscarded($this->expr);
    }

    /**
     * Is this defaulted value compared, `==` or `!=`, to its own fallback — `(x or '') != ''` — so that
     * absent and empty reach the same branch and nothing records which it was?
     */
    public function isComparedToItsFallback(): bool
    {
        return $this->expr->fallback()->isSomeAnd(fn (Expr $fallback): bool => $this->module->wrapperOf($this->expr)->isSomeAnd(
            fn (Expr $around): bool => $around->equatesWith($this->expr, $fallback),
        ));
    }

    /**
     * Is this one link of a larger `or` chain rather than the chain itself?
     */
    public function isInsideOr(): bool
    {
        return $this->module->wrapperOf($this->expr)->isSomeAnd(static fn (Expr $around): bool => $around->isOr());
    }

    /**
     * Is this what a `return` statement hands back?
     */
    public function isReturnedValue(): bool
    {
        return $this->module->ownerOf($this->expr)->isSomeAnd(fn (Node $owner): bool => $owner->returnedValue()->isSomeAnd(fn (Expr $value): bool => $value === $this->expr));
    }

    /**
     * The type mypy resolved for this expression in $codebase — none where it resolved nothing, or no
     * bridge ran.
     *
     * @return Option<Type>
     */
    public function typeIn(Codebase $codebase): Option
    {
        return $codebase->types()->at($this->module->file, $this->expr->start, $this->expr->end);
    }

    /**
     * Is this the whole value an assignment stores — `obj = info and info.weakref()`?
     */
    public function isAssignedValue(): bool
    {
        return $this->module->ownerOf($this->expr)->isSomeAnd(fn (Node $owner): bool => ($owner instanceof Assign || $owner instanceof AnnAssign) && $owner->value === $this->expr);
    }

    /**
     * Is this dict display the wire shape of one object that already has a type — every value read off
     * `self`, or off one parameter of its function?
     */
    public function isProjection(): bool
    {
        $name = $this->expr->projectedName();

        return $name !== '' && $this->module->functionOf($this->expr)->isSomeAnd(
            static fn (FunctionDef $function): bool => in_array($name, array_map(static fn (Param $param): string => $param->name, $function->params), true),
        );
    }

    /**
     * Is this written in a function whose contract is not its own to change — a dunder protocol method,
     * or one overriding its base?
     */
    public function isInContractMethod(Codebase $codebase): bool
    {
        return $this->module->functionOf($this->expr)->isSomeAnd(fn (FunctionDef $function): bool => (str_starts_with($function->name, '__') && str_ends_with($function->name, '__'))
            || $codebase->index()->isOverride($function, $this->module));
    }

    /**
     * Is this written in a function annotated to return a `TypedDict` the codebase declares — a shape
     * already typed, statically checked?
     */
    public function isInTypedDictFunction(Codebase $codebase): bool
    {
        return $this->module->functionOf($this->expr)->isSomeAnd(static fn (FunctionDef $function): bool => $function->returns !== null
            && $codebase->typedDicts()->isTypedDict($function->returns->dottedName()));
    }

    /**
     * Is this call a dataclass method rebuilding its own object by hand — the sole `return`, carrying at
     * least three fields across as `self.x` and changing at least one — where `dataclasses.replace`
     * would say only what changes?
     */
    public function isHandRolledReplace(): bool
    {
        $arguments = $this->expr->isCall() ? $this->expr->get('arguments') : [];

        if ($arguments === [] || array_any($arguments, static fn (Expr $argument): bool => $argument->is(ExprKind::Starred))) {
            return false;
        }

        $carried = count(array_filter($arguments, static fn (Expr $argument): bool => $argument->argumentValue()->isOwnAttributeRead()));

        return $carried >= 3 && $carried < count($arguments) && $this->isSoleReturnOfOwnDataclass();
    }

    /**
     * Is this the only statement of a method of a dataclass, returning a new object of that class?
     */
    private function isSoleReturnOfOwnDataclass(): bool
    {
        return $this->module->functionOf($this->expr)->isSomeAnd(function (FunctionDef $method): bool {
            $body = $method->body->body;
            $class = $this->module->parentOf($method)->andThen(fn (Node $block): Option => $this->module->parentOf($block));

            return count($body) === 1
                && $body[0]->returnedValue()->isSomeAnd(fn (Expr $value): bool => $value === $this->expr)
                && $class->isSomeAnd(fn (Node $owner): bool => $owner instanceof ClassDef && $owner->isDataclass() && $this->expr->constructs($owner->name));
        });
    }

    /**
     * Is this written in a function annotated to return a sequence of one kind?
     */
    public function isInSequenceFunction(): bool
    {
        return $this->module->functionOf($this->expr)->isSomeAnd(static fn (FunctionDef $function): bool => $function->returns?->isSequenceType() === true);
    }

    /**
     * Does this call build a dataclass the codebase declares and hand `""` to a field it requires as text?
     */
    public function fillsRequiredTextWithBlank(Dataclasses $dataclasses): bool
    {
        return $this->expr->isCall() && $dataclasses->named($this->expr->get('callee')->dottedName())->isSomeAnd(
            fn (ClassDef $class): bool => array_intersect($this->expr->fieldsHandedBlank($class->initFieldNames()), $class->requiredTextFields()) !== [],
        );
    }

    /**
     * The names of the parameters a caller of $function supplies — every one but a bound method's first.
     *
     * @return list<string>
     */
    private function handedIn(FunctionDef $function): array
    {
        $names = array_map(static fn (Param $param): string => $param->name, $function->params);

        return $this->module->isMethod($function) && ! $function->isStatic() ? array_slice($names, 1) : $names;
    }

    /**
     * Is this the outermost `and` of a condition with substance — one at least of its conditions more than a
     * bare `isinstance` (a pure type check is the type-guard rule's), and two or more attribute reaches
     * between them, counted through the locals it reads? `x and y` asks nothing worth a name.
     */
    public function isSubstantiveGuard(): bool
    {
        if (! $this->isOutermostAnd()) {
            return false;
        }

        $conjuncts = $this->resolvedConjuncts();

        return ! array_all($conjuncts, static fn (Expr $conjunct): bool => $conjunct->isInstanceCheck())
            && array_sum(array_map(static fn (Expr $conjunct): int => $conjunct->reachCount(), $conjuncts)) >= 2;
    }

    /**
     * Is this the outermost `and` of a chain that narrows a value through two or more `isinstance` checks —
     * `isinstance(n, Call) and isinstance(n.func, Attribute)`? One chain is one finding.
     */
    public function isTypeNarrowingGuard(): bool
    {
        return $this->isOutermostAnd()
            && count(array_filter($this->expr->conjuncts(), static fn (Expr $conjunct): bool => $conjunct->isInstanceCheck())) >= 2;
    }

    /**
     * Is this an `and` no other `and` holds — the root of its chain?
     */
    private function isOutermostAnd(): bool
    {
        return $this->expr->isAnd() && ! $this->module->wrapperOf($this->expr)->isSomeAnd(static fn (Expr $around): bool => $around->isAnd());
    }

    /**
     * This condition's fingerprint, blind to the ORDER of its conditions and to a local standing in for one
     * — `o.paid and o.lines` and `o.lines and paid` (with `paid = o.paid`) are one question.
     */
    public function guardFingerprint(): string
    {
        $hashes = array_map(static fn (Expr $conjunct): string => StructuralHash::ofExpression($conjunct), $this->resolvedConjuncts());
        sort($hashes);

        return sha1(implode('|', $hashes));
    }

    /**
     * The conditions this `and` joins, a local assigned once read as the value it was assigned.
     *
     * @return list<Expr>
     */
    private function resolvedConjuncts(): array
    {
        $aliases = $this->module->functionOf($this->expr)->map(static fn (FunctionDef $function): array => $function->soleAssignments())->unwrapOr([]);

        return array_map(
            static fn (Expr $conjunct): Expr => $conjunct->is(ExprKind::Name) && isset($aliases[$conjunct->get('name')]) ? $aliases[$conjunct->get('name')] : $conjunct,
            $this->expr->conjuncts(),
        );
    }

    /**
     * Is this `isinstance` the head of a type switch — the first of two or more tests on one subject, each
     * the whole condition of a different `if` in its function, over two or more classes? The value is
     * asked what it IS instead of told what to do.
     */
    public function isTypeSwitchHead(): bool
    {
        $arms = $this->typeSwitchArms();

        return count($arms) >= 2 && $arms[0]->test === $this->expr && count(array_unique($this->typeSwitchClasses())) >= 2;
    }

    /**
     * The classes the switch this test belongs to asks about, one per arm.
     *
     * @return list<string>
     */
    public function typeSwitchClasses(): array
    {
        return array_map(static fn (IfStmt $arm): string => $arm->test->get('arguments')[1]->dottedName(), $this->typeSwitchArms());
    }

    /**
     * Does every arm of this switch hand the subject to a call and return what it gives — a MAPPER turning
     * the value into another type, which the code owning that type is the right home for?
     */
    public function typeSwitchTranslatesEveryArm(): bool
    {
        $subject = $this->switchSubject();

        return array_all($this->typeSwitchArms(), static fn (IfStmt $arm): bool => $arm->body->translates($subject));
    }

    /**
     * Is this written in a named constructor — `@classmethod` building `cls(...)` from what it is handed,
     * the one place a value's type decides how the class is born?
     */
    public function isInFromSourceFactory(): bool
    {
        return $this->module->functionOf($this->expr)->isSomeAnd(static fn (FunctionDef $function): bool => $function->isNamedConstructor());
    }

    /**
     * The `if`s in this test's function whose whole condition is a type test of the same subject, in the
     * order they are written — none when this is no type test.
     *
     * @return list<IfStmt>
     */
    private function typeSwitchArms(): array
    {
        if (! $this->expr->isTypeTest()) {
            return [];
        }

        $subject = $this->switchSubject();
        $tests = $this->module->functionOf($this->expr)->map(fn (FunctionDef $function): array => array_values(array_filter(
            $this->module->expressionsIn($function),
            fn (Expr $test): bool => $test->isTypeTest()
                && StructuralHash::ofExpression($test->get('arguments')[0]) === $subject
                && $this->module->ownerOf($test)->isSomeAnd(fn (Node $owner): bool => $owner instanceof IfStmt && $owner->test === $test && $this->isSwitchArm($owner)),
        )))->unwrapOr([]);

        return array_map(fn (Expr $test): IfStmt => $this->module->ownerOf($test)->unwrap(), $tests);
    }

    /**
     * Does $if branch as one arm of a choice — a link of an `if`/`elif` chain, or an `if` that leaves? A
     * lone `if` that falls through only adjusts the value on its way.
     */
    private function isSwitchArm(IfStmt $if): bool
    {
        return $if->else !== null
            || $if->body->hasTrailingExit()
            || $this->module->parentOf($if)->isSomeAnd(static fn (Node $parent): bool => $parent instanceof IfStmt && $parent->else === $if);
    }

    /**
     * The fingerprint of the value this type test asks about.
     */
    private function switchSubject(): string
    {
        return StructuralHash::ofExpression($this->expr->get('arguments')[0]);
    }

    /**
     * The one class every argument of this call is asked of — `text(order.status == "paid", order.total > 100)`
     * answers with the order's class, because both arguments are answers the same object gave. None when an
     * argument asks nothing (a literal, a bare name), when mypy typed no receiver, or when the arguments
     * disagree about whose answers they carry.
     *
     * @return Option<string>
     */
    public function argumentSubjectType(Codebase $codebase): Option
    {
        $arguments = array_map(static fn (Expr $argument): Expr => $argument->is(ExprKind::Keyword) ? $argument->get('value') : $argument, $this->expr->isCall() ? $this->expr->get('arguments') : []);
        $types = [];

        foreach ($arguments as $argument) {
            $asks = array_filter($argument->flatten(), static fn (Expr $part): bool => $part->is(ExprKind::Attribute));

            if ($asks === []) {
                return Option::none();
            }

            foreach ($asks as $ask) {
                $receiver = $ask->get('object');
                $class = $codebase->types()->at($this->module->file, $receiver->start, $receiver->end)->andThen(static fn (Type $type) => $type->className());

                if ($class->isNone()) {
                    return Option::none();
                }

                $types[$class->unwrap()] = true;
            }
        }

        return count($types) === 1 ? Option::some(array_key_first($types)) : Option::none();
    }
}
