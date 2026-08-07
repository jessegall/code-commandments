<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeFinder;

/**
 * Resolves the declared type of an expression through the receiver chain (field/method hops),
 * conservatively yielding null for unresolvable links. Underpins value-flow analysis.
 */
final class TypeResolver
{
    use MemoisedPerCodebase;

    /**
     * @var array<string, array<string, ?string>>  fqcn => field => declared type fqcn
     */
    private array $fieldType = [];

    /**
     * @var array<string, array<string, ?string>>  fqcn => method => return type fqcn
     */
    private array $returnType = [];

    /**
     * @var array<string, array<string, array<int, bool>>>  fqcn => method => pos => param is nullable
     */
    private array $paramNullable = [];

    /**
     * @var array<string, array<string, bool>>  fqcn => method => declares a variadic parameter
     */
    private array $methodVariadic = [];

    /**
     * @var array<string, array<string, string>>  fqcn => field => the element type its collection holds
     */
    private array $collectionElement = [];

    /**
     * @var array<string, string>  fqcn => parent fqcn (so a field/type walks up the hierarchy)
     */
    private array $parentOf = [];

    /**
     * @var array<string, list<string>>  fqcn => the traits it uses (so a trait-provided method resolves)
     */
    private array $traitsOf = [];

    /**
     * @var array<int, array<string, ?string>>  object-id of a function => local var => type
     */
    private array $localCache = [];

    private function __construct(Codebase $codebase)
    {
        $this->index($codebase);
    }

    /**
     * The type $expr evaluates to inside $function, or null when it can't be resolved cheaply.
     */
    public function typeOf(Node $expr, FunctionLike $function, ?string $selfFqcn): ?string
    {
        return $this->resolve($expr, $this->localTypes($function, $selfFqcn), $selfFqcn);
    }

    /**
     * Is the $pos-th parameter of $fqcn::$method nullable (or absent from the index)? Null when the
     * signature isn't known — the caller must not treat "unknown" as "non-nullable".
     */
    public function paramIsNullable(?string $fqcn, string $method, int $pos): ?bool
    {
        return $this->paramNullable[ltrim((string) $fqcn, '\\')][$method][$pos] ?? null;
    }

    /**
     * The class that actually DECLARES $field — $fqcn itself, or the nearest ancestor that declares it
     * (an inherited field belongs to its parent). Null when no class in the chain declares it. Lets a
     * `$subclass->inheritedField` read attribute to the base where the field — and its nullability — lives.
     */
    public function declaringClassOf(?string $fqcn, string $field): ?string
    {
        $seen = [];

        for ($class = ltrim((string) $fqcn, '\\'); $class !== '' && ! isset($seen[$class]); $class = $this->parentOf[$class] ?? '') {
            $seen[$class] = true;

            if (array_key_exists($field, $this->fieldType[$class] ?? [])) {
                return $class;
            }
        }

        return null;
    }

    /**
     * The class that actually DECLARES $method reachable from $fqcn — $fqcn itself, or the nearest ancestor
     * that declares it. Lets calls to an inherited method (on different subclasses of one base) group to the
     * single owning class. Null when no class in the chain declares it.
     */
    public function declaringClassOfMethod(?string $fqcn, string $method): ?string
    {
        $seen = [];

        for ($class = ltrim((string) $fqcn, '\\'); $class !== '' && ! isset($seen[$class]); $class = $this->parentOf[$class] ?? '') {
            $seen[$class] = true;

            $owner = $this->methodDeclaredBy($class, $method, []);

            if ($owner !== null) {
                return $owner;
            }
        }

        return null;
    }

    /**
     * The class OR trait that declares $method — $class itself, or a trait it uses (recursively), so a
     * `with`-style method provided by a shared trait resolves to the TRAIT, unifying every type that uses it.
     *
     * @param  array<string, true>  $seen
     */
    private function methodDeclaredBy(string $class, string $method, array $seen): ?string
    {
        if (isset($seen[$class])) {
            return null;
        }

        $seen[$class] = true;

        if (array_key_exists($method, $this->returnType[$class] ?? [])) {
            return $class;
        }

        foreach ($this->traitsOf[$class] ?? [] as $trait) {
            $owner = $this->methodDeclaredBy($trait, $method, $seen);

            if ($owner !== null) {
                return $owner;
            }
        }

        return null;
    }

    /**
     * Does $method (resolved to its declaring class from $fqcn) declare a VARIADIC parameter — the shape of a
     * `with`-style API (`copyWith(mixed ...$changes)`) that maps named arguments to fields? False when unknown.
     */
    public function methodIsVariadic(?string $fqcn, string $method): bool
    {
        $owner = $this->declaringClassOfMethod($fqcn, $method);

        return $owner !== null && ($this->methodVariadic[$owner][$method] ?? false);
    }

    /**
     * The declared type of $field on $fqcn — resolved to the class that actually DECLARES it, so an
     * inherited property reads the type from its base. Null when the field (or its type) isn't known.
     */
    public function propertyTypeOf(?string $fqcn, string $field): ?string
    {
        $owner = $this->declaringClassOf($fqcn, $field);

        return $owner === null ? null : ($this->fieldType[$owner][$field] ?? null);
    }

    /**
     * The element type $field's collection holds — the X each item is. Stated by a
     * `#[DataCollectionOf(X::class)]` or, where PHP's own type stops at the container, by the
     * docblock (`@var list<X>`, `X[]`, `Collection<int, X>` — {@see DocType}). Resolved to the
     * declaring class, so an inherited collection reads its base's declaration. Null when $field
     * declares no element type anywhere.
     */
    public function collectionElementOf(?string $fqcn, string $field): ?string
    {
        $owner = $this->declaringClassOf($fqcn, $field);

        return $owner === null ? null : ($this->collectionElement[$owner][$field] ?? null);
    }

    private function resolve(Node $expr, array $locals, ?string $selfFqcn): ?string
    {
        if ($expr instanceof Variable && is_string($expr->name)) {
            return $expr->name === 'this' ? $selfFqcn : ($locals[$expr->name] ?? null);
        }

        if ($expr instanceof New_ && $expr->class instanceof Name) {
            return ltrim($expr->class->toString(), '\\');
        }

        if ($expr instanceof StaticCall && $expr->class instanceof Name && $expr->name instanceof Identifier) {
            $class = ltrim($expr->class->toString(), '\\');
            $method = $expr->name->toString();
            $return = $this->returnType[$class][$method] ?? null;

            // A `static`/`self` return (a named constructor like `::for()`, `::make()`), OR a magic
            // `::from()`/`::from<Type>()` factory whose inherited signature we can't see, yields the
            // class itself; any other static call resolves through its declared return type.
            if (self::isSelfReferential($return) || ($return === null && str_starts_with($method, 'from'))) {
                return $class;
            }

            return $return;
        }

        if ($expr instanceof PropertyFetch && $expr->name instanceof Identifier) {
            $receiver = $this->resolve($expr->var, $locals, $selfFqcn);
            $owner = $receiver === null ? null : $this->declaringClassOf($receiver, $expr->name->toString());

            return $owner === null ? null : ($this->fieldType[$owner][$expr->name->toString()] ?? null);
        }

        if (($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall) && $expr->name instanceof Identifier) {
            $receiver = $this->resolve($expr->var, $locals, $selfFqcn);

            if ($receiver === null) {
                return null;
            }

            // A fluent method typed `static`/`self` (`->withFoo(): static`) stays on the receiver.
            $return = $this->returnType[$receiver][$expr->name->toString()] ?? null;

            return self::isSelfReferential($return) ? $receiver : $return;
        }

        // `$a ?? $b` and `$c ? $a : $b` evaluate to one of their branches — the type of whichever
        // branch we can resolve.
        if ($expr instanceof Coalesce) {
            return $this->resolve($expr->left, $locals, $selfFqcn) ?? $this->resolve($expr->right, $locals, $selfFqcn);
        }

        if ($expr instanceof Ternary) {
            return $this->resolve($expr->if ?? $expr->cond, $locals, $selfFqcn) ?? $this->resolve($expr->else, $locals, $selfFqcn);
        }

        return null;
    }

    /**
     * The local variables of $function typed from their assignment ORIGIN — resolved in source
     * order so a later assignment can lean on an earlier one. Memoised per function.
     *
     * @return array<string, ?string>
     */
    private function localTypes(FunctionLike $function, ?string $selfFqcn): array
    {
        $id = spl_object_id($function);

        if (isset($this->localCache[$id])) {
            return $this->localCache[$id];
        }

        // A closure/arrow-fn inherits the types of the variables it CAPTURES from the enclosing
        // scope — a captured var IS the outer one, so this is exact, not inference.
        $locals = $this->capturedTypes($function, $selfFqcn);

        foreach ($function->getParams() as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name)) {
                $locals[$param->var->name] = self::typeName($param->type);
            }
        }

        foreach (new NodeFinder()->findInstanceOf($function, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && is_string($assign->var->name)) {
                $locals[$assign->var->name] = $this->resolve($assign->expr, $locals, $selfFqcn);
            }
        }

        // `foreach ($this->songs as $song)` types $song from the collection's declared element type.
        foreach (new NodeFinder()->findInstanceOf($function, Foreach_::class) as $each) {
            if (! ($each->valueVar instanceof Variable && is_string($each->valueVar->name) && $each->expr instanceof PropertyFetch && $each->expr->name instanceof Identifier)) {
                continue;
            }

            $owner = $this->resolve($each->expr->var, $locals, $selfFqcn);
            $element = $owner === null ? null : ($this->collectionElement[$owner][$each->expr->name->toString()] ?? null);

            if ($element !== null) {
                $locals[$each->valueVar->name] = $element;
            }
        }

        return $this->localCache[$id] = $locals;
    }

    /**
     * Record the element type a field's DOCBLOCK declares (`@var list<Payment>`, `Payment[]`,
     * `@param Collection<int, Payment> $payments`), resolved through the file's imports
     * ({@see DocType}). PHP types the container and stops, so without this a `foreach` over an
     * ordinary typed collection loses its loop variable and every answer about it goes unresolved
     * (#449). A `#[DataCollectionOf]` is the stronger statement and is never overwritten.
     */
    private function recordDocumentedElement(string $fqcn, string $field, ?string $docblock, ?string $variable, ParsedFile $file): void
    {
        if (isset($this->collectionElement[$fqcn][$field])) {
            return;
        }

        $written = DocType::elementNamed($docblock, $variable);

        if ($written !== null) {
            $this->collectionElement[$fqcn][$field] = DocType::resolve($written, $file);
        }
    }

    /**
     * Record the element type a field's `#[DataCollectionOf(X::class)]` declares, so a `foreach` over
     * that typed collection can type its loop variable. The one Spatie-specific read in the resolver —
     * kept to this method — because that attribute is the only place the element type is stated.
     *
     * @param  list<\PhpParser\Node\AttributeGroup>  $attrGroups
     */
    private function recordCollectionElement(string $fqcn, string $field, array $attrGroups): void
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (! str_ends_with($attribute->name->toString(), 'DataCollectionOf')) {
                    continue;
                }

                $arg = $attribute->args[0]->value ?? null;

                $element = match (true) {
                    $arg instanceof ClassConstFetch && $arg->class instanceof Name => ltrim($arg->class->toString(), '\\'),
                    $arg instanceof String_ => ltrim($arg->value, '\\'),
                    default => null,
                };

                if ($element !== null) {
                    $this->collectionElement[$fqcn][$field] = $element;
                }
            }
        }
    }

    /**
     * The variable types a nested closure/arrow-fn carries in from its enclosing function — an arrow
     * function auto-captures the whole outer scope by value; a closure captures only its `use (...)`
     * variables. A plain function captures nothing.
     *
     * @return array<string, ?string>
     */
    private function capturedTypes(FunctionLike $function, ?string $selfFqcn): array
    {
        $enclosing = $this->enclosingFunction($function);

        if ($enclosing === null) {
            return [];
        }

        $outer = $this->localTypes($enclosing, $selfFqcn);

        if ($function instanceof ArrowFunction) {
            return $outer;
        }

        if (! $function instanceof Closure) {
            return [];
        }

        $captured = [];

        foreach ($function->uses as $use) {
            if ($use->var instanceof Variable && is_string($use->var->name)) {
                $captured[$use->var->name] = $outer[$use->var->name] ?? null;
            }
        }

        return $captured;
    }

    private function enclosingFunction(FunctionLike $function): ?FunctionLike
    {
        for ($node = $function->getAttribute('parent'); $node !== null; $node = $node->getAttribute('parent')) {
            if ($node instanceof FunctionLike) {
                return $node;
            }
        }

        return null;
    }

    private function index(Codebase $codebase): void
    {
        $finder = new NodeFinder();

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, ClassLike::class) as $class) {
                $fqcn = ltrim(($class->namespacedName ?? null)?->toString() ?? '', '\\');

                if ($fqcn === '') {
                    continue;
                }

                if ($class instanceof Class_ && $class->extends instanceof Name) {
                    $this->parentOf[$fqcn] = ltrim($class->extends->toString(), '\\');
                }

                foreach ($class->getTraitUses() as $use) {
                    foreach ($use->traits as $trait) {
                        $this->traitsOf[$fqcn][] = ltrim($trait->toString(), '\\');
                    }
                }

                $constructor = $class instanceof Class_ ? $class->getMethod('__construct')?->getDocComment()?->getText() : null;

                foreach (AstNode::constructorParamsOf($class) as $param) {
                    if (! ($param->flags !== 0 && $param->var instanceof Variable && is_string($param->var->name))) {
                        continue;
                    }

                    $this->fieldType[$fqcn][$param->var->name] = self::typeName($param->type);
                    $this->recordCollectionElement($fqcn, $param->var->name, $param->attrGroups);
                    $this->recordDocumentedElement($fqcn, $param->var->name, $param->getDocComment()?->getText() ?? $constructor, $param->var->name, $file);
                }

                foreach ($class->getProperties() as $property) {
                    foreach ($property->props as $declared) {
                        $this->fieldType[$fqcn][$declared->name->toString()] = self::typeName($property->type);
                        $this->recordCollectionElement($fqcn, $declared->name->toString(), $property->attrGroups);
                        $this->recordDocumentedElement($fqcn, $declared->name->toString(), $property->getDocComment()?->getText(), null, $file);
                    }
                }

                foreach ($class->getMethods() as $method) {
                    $this->returnType[$fqcn][$method->name->toString()] = self::typeName($method->returnType);
                    $this->methodVariadic[$fqcn][$method->name->toString()] = self::hasVariadicParam($method);

                    foreach (array_values($method->params) as $pos => $param) {
                        $this->paramNullable[$fqcn][$method->name->toString()][$pos] = self::paramAcceptsNull($param);
                    }
                }
            }
        }
    }

    /**
     * Can this parameter receive `null`? True when its type admits null — nullable (`?T`/`T|null`),
     * untyped, or `mixed` — or when it defaults to the null literal. A non-null default (`int $x = 0`)
     * does NOT make it null-accepting; that's the difference between "optional" and "nullable".
     */
    private static function hasVariadicParam(Node\Stmt\ClassMethod $method): bool
    {
        foreach ($method->params as $param) {
            if ($param->variadic) {
                return true;
            }
        }

        return false;
    }

    public static function paramAcceptsNull(Node\Param $param): bool
    {
        return $param->type === null
            || $param->type instanceof NullableType
            || ($param->type instanceof Identifier && strtolower($param->type->toString()) === 'mixed')
            || ($param->type instanceof Node\UnionType && self::unionHasNull($param->type))
            || ($param->default instanceof ConstFetch && $param->default->name->toLowerString() === 'null');
    }

    private static function unionHasNull(Node\UnionType $type): bool
    {
        foreach ($type->types as $member) {
            if ($member instanceof Identifier && strtolower($member->toString()) === 'null') {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this declared return type `static` or `self` — a self-referential type that resolves to the
     * class the call is made on, not to a class literally named "static"/"self"?
     */
    private static function isSelfReferential(?string $type): bool
    {
        return $type === 'static' || $type === 'self';
    }

    private static function typeName(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return self::typeName($type->type);
        }

        return $type instanceof Name ? ltrim($type->toString(), '\\') : null;
    }
}
