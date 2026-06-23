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
 * Find methods that hand-roll object hydration.
 *
 * Array-to-object: reading several statically-known keys out of an array
 * (subscripts, Arr::get, data_get, destructuring) and feeding them into an
 * instantiation — Spatie Data's `::from($row)` reimplemented by hand.
 * Flagged when the method instantiates its own class and the body reads
 * >= min distinct known keys, or when any `new <Class>(...)`'s arguments
 * read >= min distinct known keys.
 *
 * Object-to-object: building a FOREIGN class field-by-field from one
 * source object's properties (`new OutputPort(name: $port->name, ...)`).
 * That mapping belongs on the target DTO as a named factory or wither —
 * so construction inside the target class itself and `$this`-sourced
 * reads (the fix) are exempt. When the source's declared type equals the
 * target class it's a copy-with-changes, reported as such.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindManualHydration implements Pipe
{
    use ExtractsLineSnippet;

    /**
     * Arr:: methods that read a single key from an array.
     */
    private const ARR_READ_METHODS = ['get', 'pull', 'has', 'exists', 'add'];

    /**
     * Global helpers that read a single key/path from an array.
     */
    private const READ_FUNCTIONS = ['data_get'];

    private int $minKeyReads = 2;

    private int $minPropertyReads = 3;

    public function withMinKeyReads(int $min): self
    {
        $this->minKeyReads = max(1, $min);

        return $this;
    }

    public function withMinPropertyReads(int $min): self
    {
        $this->minPropertyReads = max(1, $min);

        return $this;
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

        foreach ($classLikes as $classLike) {
            $ownName = $classLike->name?->toString();

            foreach ($classLike->getMethods() as $method) {
                $hydration = $this->inspectMethod($method, $ownName, $input->useStatements);

                if ($hydration === null) {
                    continue;
                }

                $label = ($ownName !== null ? $ownName . '::' : T_String::empty()) . $method->name->toString() . '()';
                $line = $method->getStartLine();

                $matches[] = new MatchResult(
                    name: $method->name->toString(),
                    pattern: T_String::empty(),
                    match: $label,
                    line: $line,
                    offset: null,
                    content: $this->lineSnippet($input->content, $line),
                    groups: [
                        'method' => $label,
                        'kind' => $hydration['kind'],
                        'count' => (string) count($hydration['keys']),
                        'keys' => implode(', ', array_slice($hydration['keys'], 0, 5)),
                        'source' => $hydration['source'],
                        'target' => $hydration['target'],
                    ],
                );
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * Describe the first hand-rolled hydration in the method, null when
     * the method is clean.
     *
     * @param  array<string, string>  $useStatements
     * @return array{kind: string, keys: list<string>, source: string, target: string}|null
     */
    private function inspectMethod(Node\Stmt\ClassMethod $method, ?string $ownName, array $useStatements): ?array
    {
        if ($method->stmts === null || $method->stmts === []) {
            return null;
        }

        $nodeFinder = new NodeFinder;

        /** @var array<Expr\New_> $instantiations */
        $instantiations = $nodeFinder->findInstanceOf($method->stmts, Expr\New_::class);

        if ($instantiations === []) {
            return null;
        }

        $methodKeys = $this->collectKeyReads($method->stmts, $useStatements);
        $paramTypes = $this->collectParamTypes($method);

        foreach ($instantiations as $new) {
            if ($this->instantiatesOwnClass($new, $ownName) && count($methodKeys) >= $this->minKeyReads) {
                return ['kind' => 'array', 'keys' => $methodKeys, 'source' => T_String::empty(), 'target' => T_String::empty()];
            }

            $argKeys = $this->collectKeyReads($this->argExpressions($new), $useStatements);

            if (count($argKeys) >= $this->minKeyReads) {
                return ['kind' => 'array', 'keys' => $argKeys, 'source' => T_String::empty(), 'target' => T_String::empty()];
            }

            $objectMapping = $this->inspectObjectMapping($new, $ownName, $paramTypes);

            if ($objectMapping !== null) {
                return $objectMapping;
            }
        }

        return null;
    }

    /**
     * Detect a foreign class built field-by-field from one source object's
     * properties. Construction of the method's own class is exempt — named
     * factories and withers on the target ARE the fix — as are `$this`
     * reads (wither implementations).
     *
     * @param  array<string, string>  $paramTypes  var name => short type
     * @return array{kind: string, keys: list<string>, source: string, target: string}|null
     */
    private function inspectObjectMapping(Expr\New_ $new, ?string $ownName, array $paramTypes): ?array
    {
        if (! $new->class instanceof Node\Name) {
            return null;
        }

        $target = $new->class->getLast();

        if ($target === 'self' || $target === 'static' || $target === $ownName) {
            return null;
        }

        $bySource = $this->collectPropertyReadsBySource($this->argExpressions($new));

        foreach ($bySource as $source => $properties) {
            if (count($properties) < $this->minPropertyReads) {
                continue;
            }

            $kind = ($paramTypes[$source] ?? null) === $target ? 'object_copy' : 'object_mapping';

            return [
                'kind' => $kind,
                'keys' => array_keys($properties),
                'source' => '$' . $source,
                'target' => $target,
            ];
        }

        return null;
    }

    /**
     * Distinct property names read per source variable in the subtree.
     * `$this` is never a source — copying own state into another object
     * is a builder/wither, not hydration gone astray.
     *
     * @param  array<Node>  $nodes
     * @return array<string, array<string, true>>
     */
    private function collectPropertyReadsBySource(array $nodes): array
    {
        $nodeFinder = new NodeFinder;
        $bySource = [];

        $fetches = $nodeFinder->find(
            $nodes,
            fn (Node $n): bool => $n instanceof Expr\PropertyFetch || $n instanceof Expr\NullsafePropertyFetch,
        );

        foreach ($fetches as $fetch) {
            if (! $fetch->var instanceof Expr\Variable
                || ! is_string($fetch->var->name)
                || $fetch->var->name === 'this'
                || ! $fetch->name instanceof Node\Identifier
            ) {
                continue;
            }

            $bySource[$fetch->var->name][$fetch->name->toString()] = true;
        }

        return $bySource;
    }

    /**
     * Short type name per typed parameter of the method and any nested
     * closures/arrow functions, for copy-vs-mapping classification.
     *
     * @return array<string, string>
     */
    private function collectParamTypes(Node\Stmt\ClassMethod $method): array
    {
        $nodeFinder = new NodeFinder;
        $types = [];

        $functionLikes = [
            $method,
            ...$nodeFinder->find(
                $method->stmts ?? [],
                fn (Node $n): bool => $n instanceof Expr\Closure || $n instanceof Expr\ArrowFunction,
            ),
        ];

        foreach ($functionLikes as $functionLike) {
            foreach ($functionLike->params as $param) {
                if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                    continue;
                }

                $short = $this->typeShortName($param->type);

                if ($short !== null) {
                    $types[$param->var->name] = $short;
                }
            }
        }

        return $types;
    }

    private function typeShortName(?Node $type): ?string
    {
        if ($type instanceof Node\NullableType) {
            return $this->typeShortName($type->type);
        }

        if ($type instanceof Node\UnionType) {
            $named = [];

            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    continue;
                }

                if ($member instanceof Node\Name && strtolower($member->toString()) === 'null') {
                    continue;
                }

                $named[] = $member;
            }

            return count($named) === 1 ? $this->typeShortName($named[0]) : null;
        }

        if ($type instanceof Node\Name) {
            return $type->getLast();
        }

        return null;
    }

    private function instantiatesOwnClass(Expr\New_ $new, ?string $ownName): bool
    {
        if (! $new->class instanceof Node\Name) {
            return false;
        }

        $name = $new->class->getLast();

        return $name === 'self'
            || $name === 'static'
            || ($ownName !== null && $name === $ownName);
    }

    /**
     * @return list<Node>
     */
    private function argExpressions(Expr\New_ $new): array
    {
        $exprs = [];

        foreach ($new->args as $arg) {
            if ($arg instanceof Node\Arg) {
                $exprs[] = $arg->value;
            }
        }

        return $exprs;
    }

    /**
     * Collect the distinct statically-known keys read anywhere in the
     * given subtree: literal subscripts, Arr::get-family calls, data_get
     * calls, and array-destructuring assignments.
     *
     * @param  array<Node>  $nodes
     * @param  array<string, string>  $useStatements
     * @return list<string>
     */
    private function collectKeyReads(array $nodes, array $useStatements): array
    {
        $nodeFinder = new NodeFinder;
        $keys = [];

        foreach ($nodeFinder->find($nodes, fn (Node $n): bool => true) as $node) {
            if ($node instanceof Expr\ArrayDimFetch && $node->dim !== null) {
                $key = $this->knownKey($node->dim);

                if ($key !== null) {
                    $keys[$key] = true;
                }

                continue;
            }

            if ($node instanceof Expr\StaticCall
                && $node->name instanceof Node\Identifier
                && in_array($node->name->toString(), self::ARR_READ_METHODS, true)
                && $this->isArrClass($node, $useStatements)
            ) {
                $key = $this->callKeyArg($node->args);

                if ($key !== null) {
                    $keys[$key] = true;
                }

                continue;
            }

            if ($node instanceof Expr\FuncCall
                && $node->name instanceof Node\Name
                && in_array($node->name->toString(), self::READ_FUNCTIONS, true)
            ) {
                $key = $this->callKeyArg($node->args);

                if ($key !== null) {
                    $keys[$key] = true;
                }

                continue;
            }

            if ($node instanceof Expr\Assign
                && ($node->var instanceof Expr\List_ || $node->var instanceof Expr\Array_)
            ) {
                foreach ($this->destructuredKeys($node->var) as $key) {
                    $keys[$key] = true;
                }

                continue;
            }

            if ($node instanceof Node\Stmt\Foreach_
                && ($node->valueVar instanceof Expr\List_ || $node->valueVar instanceof Expr\Array_)
            ) {
                foreach ($this->destructuredKeys($node->valueVar) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  array<Node\Arg|Node\VariadicPlaceholder>  $args
     */
    private function callKeyArg(array $args): ?string
    {
        if (count($args) < 2 || ! $args[1] instanceof Node\Arg) {
            return null;
        }

        return $this->knownKey($args[1]->value);
    }

    private function knownKey(Node $dim): ?string
    {
        if ($dim instanceof Scalar\String_) {
            return $dim->value;
        }

        if ($dim instanceof Expr\ClassConstFetch
            && $dim->class instanceof Node\Name
            && $dim->name instanceof Node\Identifier
        ) {
            return $dim->class->toString() . '::' . $dim->name->toString();
        }

        if ($dim instanceof Expr\PropertyFetch
            && $dim->name instanceof Node\Identifier
            && $dim->name->toString() === 'value'
            && $dim->var instanceof Expr\ClassConstFetch
        ) {
            return $this->knownKey($dim->var) . '->value';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function destructuredKeys(Expr\List_ | Expr\Array_ $pattern): array
    {
        $keys = [];

        foreach ($pattern->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $key = $this->knownKey($item->key);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function isArrClass(Expr\StaticCall $call, array $useStatements): bool
    {
        if (! $call->class instanceof Node\Name) {
            return false;
        }

        $short = $call->class->getLast();
        $resolved = $useStatements[$short] ?? $call->class->toString();

        return $short === 'Arr'
            || $resolved === 'Arr'
            || str_ends_with($resolved, '\\Arr');
    }

}
