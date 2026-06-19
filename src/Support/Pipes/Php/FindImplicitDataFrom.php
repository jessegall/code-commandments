<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use JesseGall\PhpTypes\T_String;

/**
 * Enforce explicit Spatie Data construction:
 *
 *   - `X::from(<object>)` anywhere — the magic type-dispatch / arrayable
 *     fallback. from() should be handed an array; convert inside an explicit
 *     fromX() factory. (Enums are safe: `Enum::from()` only ever receives a
 *     scalar, never an object, so flagging objects never touches them.)
 *   - `X::from($y->toArray())` from OUTSIDE the class X — the toArray bypass;
 *     the conversion belongs in a factory. Inside X, `self::from($y->toArray())`
 *     is the blessed pattern and is allowed.
 *   - `new self()/new static()` inside a STATIC factory of a Data class — use
 *     `static::from(array)`. Copy-withers (spread `...$x`) are exempt.
 *
 * Argument types are resolved from the AST: parameter type-hints, `$this`,
 * declared/promoted property types, `new X`, and `->toArray()`/`->all()`.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindImplicitDataFrom implements Pipe
{
    private const ARRAY_RETURNING_METHODS = ['toArray', 'all', 'jsonSerialize'];

    /**
     * Eloquent model methods that decorate and RETURN the model ($this / a fresh
     * instance). `Data::from($user->append('x'))` therefore builds from an
     * object, not an array — #58. Without this the call site is classified
     * 'unknown' and missed, so #49's gating wrongly adds FromArrayOnly.
     */
    private const MODEL_FLUENT_METHODS = [
        'append', 'setAppends', 'load', 'loadMissing', 'loadCount', 'loadMorph',
        'setRelation', 'setRelations', 'unsetRelation', 'withoutRelations',
        'makeVisible', 'makeHidden', 'fresh', 'refresh', 'fill', 'forceFill',
        'setAttribute',
    ];

    private const ARRAY_BUILTINS = [
        'array_map', 'array_merge', 'array_filter', 'array_values', 'array_keys',
        'array_combine', 'array_column', 'array_diff', 'array_intersect', 'compact',
        'iterator_to_array',
    ];

    /** `<ShortClass>::<method>` calls that build an empty array. */
    private const EMPTY_ARRAY_CALLS = ['T_Array::empty', 'Arr::empty'];

    /** @var list<string> */
    private array $dataSuffixes = ['Data'];

    /**
     * @param  list<string>  $suffixes
     */
    public function withDataSuffixes(array $suffixes): self
    {
        $this->dataSuffixes = $suffixes !== [] ? array_values($suffixes) : ['Data'];

        return $this;
    }

    private function matchesDataSuffix(string $name): bool
    {
        foreach ($this->dataSuffixes as $suffix) {
            if (T_String::isNotEmpty($suffix) && str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $nodeFinder = new NodeFinder;
        $matches = [];

        /** @var array<Node\Stmt\ClassLike> $classLikes */
        $classLikes = $nodeFinder->findInstanceOf($input->ast, Node\Stmt\ClassLike::class);

        foreach ($classLikes as $class) {
            $ownName = $class->name?->toString();
            $isDataClass = $this->isDataClass($class);
            $propTypes = $this->collectPropertyTypes($class);

            foreach ($class->getMethods() as $method) {
                $paramTypes = $this->collectParamTypes($method);
                $localTypes = $this->collectLocalTypes($method);

                // new self()/static() inside a static factory of a Data class.
                if ($isDataClass && $method->isStatic() && $method->stmts !== null) {
                    foreach ($nodeFinder->findInstanceOf($method->stmts, Expr\New_::class) as $new) {
                        if (! $this->constructsOwnClass($new, $ownName) || $this->hasSpread($new)) {
                            continue;
                        }

                        // Bare `new self()` is a default instance → make(); a
                        // field-by-field `new self(a: ...)` is hand hydration.
                        $kind = $new->args === [] ? 'new_default' : 'new_mapping';
                        $matches[] = $this->match($input->content, $new->getStartLine(), $kind, $ownName ?? 'self');
                        break;
                    }
                }

                if ($method->stmts === null) {
                    continue;
                }

                foreach ($nodeFinder->findInstanceOf($method->stmts, Expr\StaticCall::class) as $call) {
                    $finding = $this->classifyFromCall($call, $ownName, $isDataClass, $paramTypes, $propTypes, $localTypes);

                    if ($finding !== null) {
                        $matches[] = $this->match($input->content, $call->getStartLine(), $finding['kind'], $finding['target']);
                    }
                }
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * @param  array<string, string>  $paramTypes
     * @param  array<string, string>  $propTypes
     * @param  array<string, string>  $localTypes
     * @return array{kind: string, target: string}|null
     */
    private function classifyFromCall(Expr\StaticCall $call, ?string $ownName, bool $isDataClass, array $paramTypes, array $propTypes, array $localTypes): ?array
    {
        if (! $call->name instanceof Node\Identifier || $call->name->toString() !== 'from') {
            return null;
        }

        if (! $call->class instanceof Node\Name) {
            return null;
        }

        $target = $call->class->getLast();

        if (count($call->args) !== 1 || ! $call->args[0] instanceof Node\Arg) {
            return null;
        }

        $isInside = in_array($target, ['self', 'static', 'parent'], true) || $target === $ownName;

        // Only a Spatie Data `::from()` carries the magic object dispatch this
        // rule is about. A same-class call counts only when THIS class is a
        // Data class; an external target must look like one (configured suffix,
        // default `Data`). A domain `Unpacker::from(Model): array` is neither.
        if ($isInside ? ! $isDataClass : ! $this->matchesDataSuffix($target)) {
            return null;
        }

        $kind = $this->classifyArg($call->args[0]->value, $paramTypes, $propTypes, $localTypes);

        if ($kind === 'empty') {
            return ['kind' => 'empty_from', 'target' => $isInside ? ($ownName ?? 'self') : $target];
        }

        if ($kind === 'object') {
            return ['kind' => 'nonarray', 'target' => $isInside ? ($ownName ?? 'self') : $target];
        }

        if ($kind === 'toarray' && ! $isInside) {
            return ['kind' => 'toarray_outside', 'target' => $target];
        }

        return null;
    }

    /**
     * @param  array<string, string>  $paramTypes
     * @param  array<string, string>  $propTypes
     * @param  array<string, string>  $localTypes
     * @return 'array'|'object'|'scalar'|'toarray'|'unknown'
     */
    private function classifyArg(Expr $arg, array $paramTypes, array $propTypes, array $localTypes): string
    {
        if ($arg instanceof Expr\Array_) {
            return $arg->items === [] ? 'empty' : 'array';
        }

        // Empty-array factories: T_Array::empty(), array() with no args.
        if ($arg instanceof Expr\StaticCall
            && $arg->class instanceof Node\Name
            && $arg->name instanceof Node\Identifier
            && in_array($arg->class->getLast() . '::' . $arg->name->toString(), self::EMPTY_ARRAY_CALLS, true)
        ) {
            return 'empty';
        }

        if ($arg instanceof Expr\FuncCall
            && $arg->name instanceof Node\Name
            && $arg->name->toString() === 'array'
            && $arg->args === []
        ) {
            return 'empty';
        }

        if ($arg instanceof Scalar\String_ || $arg instanceof Scalar\Int_ || $arg instanceof Scalar\Float_
            || $arg instanceof Expr\BinaryOp\Concat || $arg instanceof Expr\ClassConstFetch
        ) {
            return 'scalar';
        }

        if ($arg instanceof Expr\ConstFetch) {
            return 'scalar';
        }

        if (($arg instanceof Expr\MethodCall || $arg instanceof Expr\NullsafeMethodCall)
            && $arg->name instanceof Node\Identifier
            && in_array($arg->name->toString(), self::ARRAY_RETURNING_METHODS, true)
        ) {
            return 'toarray';
        }

        // A fluent model method returns the model — `Data::from($user->append(…))`
        // builds from an object (#58), so it needs an explicit factory just like
        // `Data::from($user)`.
        if (($arg instanceof Expr\MethodCall || $arg instanceof Expr\NullsafeMethodCall)
            && $arg->name instanceof Node\Identifier
            && in_array($arg->name->toString(), self::MODEL_FLUENT_METHODS, true)
        ) {
            return 'object';
        }

        if ($arg instanceof Expr\FuncCall
            && $arg->name instanceof Node\Name
            && in_array($arg->name->toString(), self::ARRAY_BUILTINS, true)
        ) {
            return 'array';
        }

        // `request()` returns the Request object — `Data::from(request())` is an
        // object dispatch (#64), just like `Data::from($request)`.
        if ($arg instanceof Expr\FuncCall
            && $arg->name instanceof Node\Name
            && strtolower($arg->name->toString()) === 'request'
            && $arg->args === []
        ) {
            return 'object';
        }

        if ($arg instanceof Expr\New_) {
            return 'object';
        }

        if ($arg instanceof Expr\Variable) {
            if ($arg->name === 'this') {
                return 'object';
            }

            return is_string($arg->name)
                ? ($paramTypes[$arg->name] ?? $localTypes[$arg->name] ?? 'unknown')
                : 'unknown';
        }

        if (($arg instanceof Expr\PropertyFetch || $arg instanceof Expr\NullsafePropertyFetch)
            && $arg->var instanceof Expr\Variable
            && $arg->var->name === 'this'
            && $arg->name instanceof Node\Identifier
        ) {
            return $propTypes[$arg->name->toString()] ?? 'unknown';
        }

        return 'unknown';
    }

    private function isDataClass(Node\Stmt\ClassLike $class): bool
    {
        if (! $class instanceof Node\Stmt\Class_ || ! $class->extends instanceof Node\Name) {
            return false;
        }

        $parent = $class->extends->getLast();

        foreach ($this->dataSuffixes as $suffix) {
            if (str_ends_with($parent, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function constructsOwnClass(Expr\New_ $new, ?string $ownName): bool
    {
        if (! $new->class instanceof Node\Name) {
            return false;
        }

        $short = $new->class->getLast();

        return $short === 'self' || $short === 'static' || ($ownName !== null && $short === $ownName);
    }

    private function hasSpread(Expr\New_ $new): bool
    {
        foreach ($new->args as $arg) {
            if ($arg instanceof Node\Arg && $arg->unpack) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>  var name => 'array'|'object'|'scalar'
     */
    private function collectParamTypes(Node\Stmt\ClassMethod $method): array
    {
        $types = [];
        $this->addParamTypes($method->params, $types);

        // Also resolve params of nested closures / arrow functions, so a
        // `Data::from($x)` inside `fn (Foo $x) => Data::from($x)` (or a
        // `function (Foo $x) {...}`) classifies $x by its hint.
        $nodeFinder = new NodeFinder;

        foreach ($nodeFinder->findInstanceOf($method->stmts ?? [], Expr\Closure::class) as $closure) {
            $this->addParamTypes($closure->params, $types);
        }

        foreach ($nodeFinder->findInstanceOf($method->stmts ?? [], Expr\ArrowFunction::class) as $arrow) {
            $this->addParamTypes($arrow->params, $types);
        }

        return $types;
    }

    /**
     * @param  array<Node\Param>  $params
     * @param  array<string, string>  $types
     */
    private function addParamTypes(array $params, array &$types): void
    {
        foreach ($params as $param) {
            if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $category = $this->categorise($param->type);

            if ($category !== null) {
                $types[$param->var->name] = $category;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function collectPropertyTypes(Node\Stmt\ClassLike $class): array
    {
        $types = [];

        foreach ($class->getProperties() as $prop) {
            $category = $this->categorise($prop->type);

            if ($category !== null) {
                foreach ($prop->props as $p) {
                    $types[$p->name->toString()] = $category;
                }
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags === 0 || ! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                    continue;
                }

                $category = $this->categorise($param->type);

                if ($category !== null) {
                    $types[$param->var->name] = $category;
                }
            }
        }

        return $types;
    }

    /**
     * First-assignment categories for locals: `$v = new X` / `[...]` / `->toArray()`.
     *
     * @return array<string, string>
     */
    private function collectLocalTypes(Node\Stmt\ClassMethod $method): array
    {
        if ($method->stmts === null) {
            return [];
        }

        $nodeFinder = new NodeFinder;
        $types = [];

        foreach ($nodeFinder->findInstanceOf($method->stmts, Expr\Assign::class) as $assign) {
            if (! $assign->var instanceof Expr\Variable || ! is_string($assign->var->name)) {
                continue;
            }

            if (isset($types[$assign->var->name])) {
                continue;
            }

            if ($assign->expr instanceof Expr\New_) {
                $types[$assign->var->name] = 'object';
            } elseif ($assign->expr instanceof Expr\Array_) {
                $types[$assign->var->name] = 'array';
            } elseif (($assign->expr instanceof Expr\MethodCall || $assign->expr instanceof Expr\NullsafeMethodCall)
                && $assign->expr->name instanceof Node\Identifier
                && in_array($assign->expr->name->toString(), self::ARRAY_RETURNING_METHODS, true)
            ) {
                $types[$assign->var->name] = 'array';
            }
        }

        return $types;
    }

    private function categorise(?Node $type): ?string
    {
        if ($type instanceof Node\NullableType) {
            return $this->categorise($type->type);
        }

        if ($type instanceof Node\UnionType) {
            $members = [];

            foreach ($type->types as $member) {
                if (($member instanceof Node\Identifier || $member instanceof Node\Name)
                    && strtolower($member->toString()) === 'null'
                ) {
                    continue;
                }

                $members[] = $member;
            }

            return count($members) === 1 ? $this->categorise($members[0]) : null;
        }

        if ($type instanceof Node\Identifier) {
            $name = strtolower($type->toString());

            if (in_array($name, ['array', 'iterable'], true)) {
                return 'array';
            }

            if (in_array($name, ['string', 'int', 'float', 'bool'], true)) {
                return 'scalar';
            }

            if ($name === 'object') {
                return 'object';
            }

            return null; // mixed, self, static, callable, never, void → unknown
        }

        if ($type instanceof Node\Name) {
            return 'object';
        }

        return null;
    }

    private function match(string $content, int $line, string $kind, string $target): MatchResult
    {
        return new MatchResult(
            name: $target,
            pattern: T_String::empty(),
            match: $target . '::from / ' . $kind,
            line: $line,
            offset: null,
            content: $this->getSnippet($content, $line),
            groups: [
                'target' => $target,
                'kind' => $kind,
            ],
        );
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode(T_String::NEWLINE, $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : T_String::empty();
    }
}
