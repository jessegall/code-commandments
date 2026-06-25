<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

/**
 * Flag `new ValueBag($v ?? [])` / `new ValueBag(T_Array::coalesce($v))` — a value
 * object built from a nullable / shape-guarded ARRAY with the null-handling and
 * `@var` shape assertion inline at the call site. Suggest a total
 * `ValueBag::coalesce($value)` factory that owns that logic once.
 *
 * "Is X a value object I can hoist a coalesce factory onto?" is answered by REAL
 * detection, not a class-name list: X must be ARRAY-CONSTRUCTIBLE — its effective
 * constructor's first parameter accepts an array. That is resolved by reflection
 * (which sees the whole inheritance chain, incl. vendor bases like Fluent /
 * Collection whose `$attributes = []` ctor is untyped-with-array-default), with
 * an AST fallback for classes that are not autoloadable. Spatie Data classes have
 * typed promoted params (not an array ctor), so they are correctly excluded.
 *
 * The value-object analogue of PreferTypeCoalesce (#106, the T_* scalars) and the
 * producer side of PreferTotalOverNullable (#108).
 */
#[IntroducedIn('1.145.0')]
class PreferCoalesceFactoryProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Hoist new ValueObject($nullableOrLoose) ceremony into a total ::coalesce() factory';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('An array-constructible value object (a class whose constructor takes an array — a Fluent bag, a collection, your own `__construct(array $x)`) is built from a null-coalescing or shape-guarding argument inline: `new Bag($v ?? [])`, `new Bag(T_Array::coalesce($v))`, `new Bag(is_array($v) ? $v : [])`.')
            ->leaveWhen('the value is already a correctly-typed array (no `??` / shape guard) — `new Bag($alreadyArray)` has no ceremony to hoist; or the class is not array-constructible (its constructor does not take an array), so it is not this pattern.')
            ->whenUnsure('add a total `static coalesce(mixed $value): static` factory on the class that does the null/shape handling once (`is_array($value) ? $value : T_Array::empty()`), and replace call sites with `Bag::coalesce($v)`.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Constructing a value object from a nullable or loosely-typed array spreads the
same null-guard and shape assertion across every call site. A total static
`::coalesce()` factory owns that logic once, so callers read as one expression.

Bad — inline null-guard + shape assertion at the call site (repeated):
    /** @var array<string, mixed> $snapshot */
    $snapshot = T_Array::coalesce($run->context_snapshot);
    $bag = new ValueBag($snapshot);

    $bag = new ValueBag($value ?? []);
    $bag = new ValueBag(is_array($value) ? $value : []);

Good — one total factory; call sites are clean:
    final class ValueBag extends Fluent
    {
        public static function coalesce(mixed $value): self
        {
            /** @var array<string, mixed> $attributes */
            $attributes = is_array($value) ? $value : T_Array::empty();

            return new self($attributes);   // the shape assertion lives here, once
        }
    }

    $bag = ValueBag::coalesce($run->context_snapshot);

It also resolves a recurring PHPStan papercut: `new Fluent($jsonDecodedArray)` fails
max level because a decoded array is `array<array-key, mixed>`, not
`array<string, mixed>`. The `coalesce()` factory is the one place the
`@var array<string, mixed>` assertion belongs.

WHAT FIRES — `new X(<arg>)` (or `X::make(<arg>)`) where X is ARRAY-CONSTRUCTIBLE
(its effective constructor's first parameter accepts an array — detected by
reflection over the full inheritance chain, AST as a fallback) and the argument
is an array coalescing / shape guard: `$v ?? []`, `$v ?? T_Array::EMPTY`,
`T_Array::coalesce($v)`, or `is_array($v) ? $v : []`.

WHAT DOES NOT — construction from an already-typed array (no guard); a class whose
constructor does not take an array (a service, or a Spatie Data with typed promoted
params); or an unresolvable class. Advisory: adding the factory is a design call.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        // Resolve names so `new X` / `extends` carry their FQCN for reflection
        // and the index lookup.
        (new NodeTraverser(new NameResolver(null, ['replaceNodes' => false])))->traverse($ast);

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Expr\New_::class) as $new) {
            if ($new->class instanceof Node\Name) {
                $this->inspect($new->class, $new->getArgs(), $new->getStartLine(), $content, $ast, $finder, $warnings);
            }
        }

        // X::make(<arg>) — the named-constructor twin.
        foreach ($finder->findInstanceOf($ast, Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name
                && $call->name instanceof Node\Identifier
                && strtolower($call->name->toString()) === 'make'
            ) {
                $this->inspect($call->class, $call->getArgs(), $call->getStartLine(), $content, $ast, $finder, $warnings);
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  list<Node\Arg>  $args
     * @param  array<Node>  $ast
     * @param  list<Warning>  $warnings
     */
    private function inspect(Node\Name $class, array $args, int $line, string $content, array $ast, NodeFinder $finder, array &$warnings): void
    {
        if (count($args) !== 1 || $args[0]->unpack || ! $this->isArrayCoalescingArg($args[0]->value)) {
            return;
        }

        $short = $class->getLast();

        if (in_array(strtolower($short), ['self', 'static', 'parent'], true) || ! $this->isArrayConstructible($class, $ast, $finder, 0)) {
            return;
        }

        $warnings[] = $this->warningAt(
            $line,
            sprintf('`%s` is built from a null-coalescing / shape-guarded array inline — add a total `%s::coalesce($value)` factory that owns the null/shape handling once, and call `%s::coalesce($v)`.', $short, $short, $short),
            $this->lineSnippet($content, $line),
            'coalesce-factory:' . $short,
        );
    }

    /**
     * Whether $arg coalesces/guards a value into an ARRAY: `$v ?? []`,
     * `$v ?? T_Array::EMPTY`, `T_Array::coalesce($v)`, or `is_array($v) ? $v : []`.
     */
    private function isArrayCoalescingArg(Expr $arg): bool
    {
        if ($arg instanceof Expr\BinaryOp\Coalesce) {
            return $this->isEmptyArray($arg->right);
        }

        if ($arg instanceof Expr\StaticCall
            && $arg->class instanceof Node\Name
            && $arg->class->getLast() === 'T_Array'
            && $arg->name instanceof Node\Identifier
            && strtolower($arg->name->toString()) === 'coalesce'
        ) {
            return true;
        }

        return $arg instanceof Expr\Ternary
            && $arg->else instanceof Expr
            && $this->isEmptyArray($arg->else)
            && $arg->cond instanceof Expr\FuncCall
            && $arg->cond->name instanceof Node\Name
            && strtolower($arg->cond->name->toString()) === 'is_array';
    }

    private function isEmptyArray(Expr $expr): bool
    {
        if ($expr instanceof Expr\Array_) {
            return $expr->items === [];
        }

        return $expr instanceof Expr\ClassConstFetch
            && $expr->class instanceof Node\Name
            && $expr->class->getLast() === 'T_Array'
            && $expr->name instanceof Node\Identifier
            && $expr->name->toString() === 'EMPTY';
    }

    /**
     * Whether X's EFFECTIVE constructor takes an array as its first parameter.
     * Reflection resolves the whole inheritance chain (incl. vendor bases like
     * Fluent / Collection). AST is the fallback when the class is not loadable.
     *
     * @param  array<Node>  $ast
     */
    private function isArrayConstructible(Node\Name $class, array $ast, NodeFinder $finder, int $depth): bool
    {
        $reflected = $this->reflectArrayConstructible($this->fqcn($class));

        if ($reflected !== null) {
            return $reflected;
        }

        if ($depth > 10) {
            return false;
        }

        $node = $this->findClassNode($class, $class->getLast(), $ast, $finder);

        if ($node === null) {
            return false;
        }

        $ctor = $node->getMethod('__construct');

        if ($ctor !== null) {
            return $this->astParamAcceptsArray($ctor->params);
        }

        // No own constructor — follow `extends`: reflect the parent (a loadable
        // vendor base resolves here), else keep walking the AST chain.
        if (! $node->extends instanceof Node\Name) {
            return false;
        }

        return $this->isArrayConstructible($node->extends, $ast, $finder, $depth + 1);
    }

    /**
     * Reflection verdict on whether $fqcn's effective constructor's first param
     * accepts an array. Null when the class is not loadable (defer to AST).
     */
    private function reflectArrayConstructible(string $fqcn): ?bool
    {
        if ($fqcn === '' || ! class_exists($fqcn)) {
            return null;
        }

        try {
            $ctor = (new \ReflectionClass($fqcn))->getConstructor();
        } catch (\Throwable) {
            return null;
        }

        if ($ctor === null) {
            return false;
        }

        $params = $ctor->getParameters();

        return $params !== [] && $this->reflectionParamAcceptsArray($params[0]);
    }

    private function reflectionParamAcceptsArray(\ReflectionParameter $param): bool
    {
        $type = $param->getType();

        if ($type instanceof \ReflectionNamedType && $this->isArrayTypeName($type->getName())) {
            return true;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($member instanceof \ReflectionNamedType && $this->isArrayTypeName($member->getName())) {
                    return true;
                }
            }
        }

        // Untyped / nullable param with an array default — how Fluent / Collection
        // declare `$attributes = []`.
        if (($type === null || $type->allowsNull()) && $param->isDefaultValueAvailable()) {
            try {
                return is_array($param->getDefaultValue());
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  list<Node\Param>  $params
     */
    private function astParamAcceptsArray(array $params): bool
    {
        if ($params === []) {
            return false;
        }

        $param = $params[0];

        if ($this->astTypeIsArrayish($param->type)) {
            return true;
        }

        // Untyped first param with an array default (`$attributes = []`).
        return $param->type === null && $param->default instanceof Expr\Array_;
    }

    private function astTypeIsArrayish(?Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return $this->astTypeIsArrayish($type->type);
        }

        if ($type instanceof Node\Identifier) {
            return $this->isArrayTypeName($type->toString());
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && $this->isArrayTypeName($member->toString())) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isArrayTypeName(string $name): bool
    {
        return in_array(strtolower($name), ['array', 'iterable'], true);
    }

    private function fqcn(Node\Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        if ($resolved instanceof Node\Name) {
            return $resolved->toString();
        }

        return $name->isFullyQualified() ? ltrim($name->toString(), '\\') : $name->toString();
    }

    /**
     * @param  array<Node>  $ast
     */
    private function findClassNode(Node\Name $class, string $short, array $ast, NodeFinder $finder): ?Node\Stmt\Class_
    {
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $node) {
            if ($node->name?->toString() === $short) {
                return $node;
            }
        }

        $fqcn = $this->fqcn($class);

        if ($this->index === null || $fqcn === '') {
            return null;
        }

        $summary = $this->index->classByFqcn(ltrim($fqcn, '\\'));

        if ($summary === null) {
            return null;
        }

        $fileContent = @file_get_contents($summary->filePath);

        if (! is_string($fileContent)) {
            return null;
        }

        $fileAst = $this->parse($fileContent);

        if ($fileAst === null) {
            return null;
        }

        (new NodeTraverser(new NameResolver(null, ['replaceNodes' => false])))->traverse($fileAst);

        foreach ((new NodeFinder)->findInstanceOf($fileAst, Node\Stmt\Class_::class) as $node) {
            if ($node->name?->toString() === $short) {
                return $node;
            }
        }

        return null;
    }

}
