<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * Top-down prop typing over the {@see ComponentGraph} — the capstone. A component that can't
 * type its own prop locally gets it from ABOVE: walk to a parent that renders it, type the
 * expression bound there in that parent's scope, and recurse. A parent's binding is often its
 * OWN prop, so the walk climbs page → child → grandchild until it reaches a root whose props
 * are typed from the server. Everything resolves because you start from the top.
 *
 * Each hop reuses the engine already built — {@see Script} for a scope's declared props and
 * locals, {@see Expr} for the bound expression's shape (a member chain becomes an indexed
 * access `Root['field']`, a literal its own type). A visited-set guards the inevitable cycles;
 * an untypable hop simply yields null, never a guess.
 */
final class PropTypes
{
    public function __construct(private readonly ComponentGraph $graph) {}

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
            ?? $this->typeOf($scope, $name, $seen); // the scope's own prop — keep climbing
    }
}
