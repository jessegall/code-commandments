<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Expr\Expr;
use JesseGall\CodeCommandments\Vue\Ts\Node\ObjectType;
use JesseGall\CodeCommandments\Vue\Ts\Parser;

/**
 * Top-down prop typing over ComponentGraph. A component that can't type its own prop walks to parents,
 * typing the bound expression in each scope, until reaching a root. Reuses Script (scoped props) and Expr (shape inference); cycles are guarded.
 */
final class PropTypes
{
    public function __construct(private readonly ComponentGraph $graph) {}

    /**
     * The typing that traces NOTHING — over an empty graph, so a component is asked only about its
     * own declarations and the climb ends immediately. What a caller that has no codebase to trace
     * through holds, instead of a null every read has to defend against.
     */
    public static function none(): self
    {
        return new self(ComponentGraph::empty());
    }

    /**
     * The type of $prop on $component — its locally declared type, or, failing that, traced
     * up the render tree to wherever the value actually originates. Null when the trail runs
     * cold.
     *
     * @param  list<string>  $seen  component#prop pairs already in progress (cycle guard)
     */
    public function typeOf(Sfc $component, string $prop, array $seen = []): ?string
    {
        $key = $component->path . '#' . $prop;

        if (in_array($key, $seen, true)) {
            return null;
        }

        $seen[] = $key;

        $local = (new Script($component->scriptContent()))->propTypes()[$prop] ?? null;

        if ($local !== null && $local !== 'unknown') {
            return $local; // the component declares it (often a root typed from the server)
        }

        foreach ($this->graph->usagesOf($component->path) as $usage) {
            if (! isset($usage->bindings[$prop])) {
                continue;
            }

            $type = $this->expressionType($usage->bindings[$prop], $usage->parent, $seen);

            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }

    /**
     * The type of an expression bound at a call site, evaluated in the PARENT's scope — a
     * member chain `order.customer` against its root's type (`Order['customer']`), a literal
     * its own type.
     *
     * @param  list<string>  $seen
     */
    private function expressionType(Expr $expression, Sfc $scope, array $seen): ?string
    {
        $chain = $expression->asChain();

        if ($chain === null) {
            return $expression->inferType(); // a literal / computed shape, or null
        }

        $type = $this->nameType($scope, $chain[0], $seen);

        if ($type === null) {
            return null;
        }

        foreach (array_slice($chain, 1) as $segment) {
            $type = "{$type}['{$segment}']"; // indexed access down the data path
        }

        return $type;
    }

    /**
     * The type of a bare name in a component's scope — a declared prop, a local
     * ref/computed/function, or (when the name is the component's OWN prop) traced one level
     * further up the tree.
     *
     * @param  list<string>  $seen
     */
    private function nameType(Sfc $scope, string $name, array $seen): ?string
    {
        $script = new Script($scope->scriptContent());

        return ($script->propTypes()[$name] ?? null)
            ?? $script->declaredType($name)
            ?? $this->composableType($scope, $script, $name) // `const { taxes } = useTaxTypes()`
            ?? $this->typeOf($scope, $name, $seen);          // the scope's own prop — keep climbing
    }

    /**
     * The type of a local pulled out of a composable — `const { taxes } = useTaxTypes()`. Follow the
     * import to the composable's file, read its declared return type, resolve THAT type's fields
     * (across modules, like any type), and take the field for $name (unwrapping its `Ref`, as the
     * template sees it). Null when the composable or its return type can't be read statically — an
     * inferred return only a type checker could resolve.
     */
    private function composableType(Sfc $scope, Script $script, string $name): ?string
    {
        $composable = $script->destructuredCall($name);
        $specifier = $composable !== null ? $script->importSpecifier($composable) : null;
        $path = $specifier !== null ? ModuleResolver::forFile($scope->path)->resolve($scope->path, $specifier) : null;

        if ($path === null) {
            return null;
        }

        $composableScript = TypeResolver::scriptOf($path);
        $returnType = $composableScript->returnTypeName($composable);

        if ($returnType === null) {
            // Inferred return — resolve the field from the composable's own `return { … }`.
            return $composableScript->inferredReturnFields($composable)[$name] ?? null;
        }

        $shape = Parser::type($returnType);
        $fields = $shape instanceof ObjectType ? $shape->fields() : TypeResolver::fields($returnType, $path, $composableScript);

        return isset($fields[$name]) ? Script::unwrapRef($fields[$name]) : null;
    }
}
