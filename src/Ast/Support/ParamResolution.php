<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;

/**
 * Decides whether a method UNPACKS its target out of a container parameter — takes a
 * container object AND a scalar key, resolves the key against the container by a
 * single-key lookup (`$workflow->graph->nodeById($nodeId)`), captures the result, and
 * then works on THAT while the container is only ever packaging.
 *
 * The decisive question is *who the method is about*. If the resolved target is the
 * subject and the container is a PURE ENCAPSULATOR — touched nowhere but the unpack,
 * and otherwise only via cheap `$owner->prop` reads — then the caller (who passed
 * both, and named the key) should resolve once and hand over the OBJECT. The method
 * should demand the type it uses, not an id plus its container.
 *
 * The container being used as a whole object downstream (passed as an argument, or a
 * method receiver — graph surgery on `$graph`, a descriptor built from `$graph`)
 * means it's a genuine co-subject, not packaging: that is NOT this sin.
 */
final class ParamResolution
{
    /**
     * The scalar types that read as a lookup KEY — an identity, not a collaborator.
     */
    private const array KEY_TYPES = ['string', 'int'];

    public function unpacksTargetFromContainerParam(AstNode $match, Codebase $codebase): bool
    {
        $method = $match->node;

        if (! $method instanceof ClassMethod || $method->stmts === null) {
            return false;
        }

        $owners = $this->objectParams($method, $codebase);
        $keys = $this->scalarKeyParams($method);

        if ($owners === [] || $keys === []) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf($method->stmts, MethodCall::class) as $call) {
            if (! $this->keyedSolelyBy($call, $keys) || ! $this->rootedAtOwner($call->var, $owners)) {
                continue;
            }

            if ($this->capturedLocal($call) === null) {
                continue;
            }

            if ($this->ownerIsPureEncapsulator($method, $this->unpackRoot($call), $call)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Param names whose type is a class — a passed-in container the caller holds.
     * Reflection is excluded (it IS a lookup mechanism, no "pass the object"
     * alternative); an enum is excluded too — it's a behaviour-bearing value, and
     * `$method->rateCents($grams)` is the good enums-with-behaviour pattern, not a
     * container being dug into.
     *
     * @return list<string>
     */
    private function objectParams(ClassMethod $method, Codebase $codebase): array
    {
        $names = [];

        foreach ($method->params as $param) {
            $type = $this->baseType($param->type);

            if ($param->var instanceof Variable && is_string($param->var->name)
                && $type instanceof Name
                && ! str_starts_with($type->getLast(), 'Reflection')
                && ! $codebase->isEnum($type->toString())) {
                $names[] = $param->var->name;
            }
        }

        return $names;
    }

    /**
     * Param names typed as a scalar identity (`string`/`int`) — a lookup key.
     *
     * @return list<string>
     */
    private function scalarKeyParams(ClassMethod $method): array
    {
        $names = [];

        foreach ($method->params as $param) {
            $type = $this->baseType($param->type);

            if ($param->var instanceof Variable && is_string($param->var->name)
                && $type instanceof Identifier && in_array($type->toString(), self::KEY_TYPES, true)) {
                $names[] = $param->var->name;
            }
        }

        return $names;
    }

    /**
     * The single underlying type of a (possibly nullable) hint, or null for a
     * union/intersection (too ambiguous to call a container or a key).
     */
    private function baseType(?Node $type): ?Node
    {
        if ($type instanceof NullableType) {
            return $type->type;
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            return null;
        }

        return $type;
    }

    /**
     * Is the call a single-key resolution — its SOLE argument is one of the key
     * params (`$owner->nodeById($id)`)? One argument that IS the identity is the
     * signature of resolving an entity by id. A call taking the key alongside other
     * arguments (`edgesFromSocket($node->id, $handle)`) is a QUERY, left alone.
     *
     * @param  list<string>  $keys
     */
    private function keyedSolelyBy(MethodCall $call, array $keys): bool
    {
        if (count($call->args) !== 1) {
            return false;
        }

        $argument = $call->args[0];

        return $argument instanceof Arg
            && $argument->value instanceof Variable
            && is_string($argument->value->name)
            && in_array($argument->value->name, $keys, true);
    }

    /**
     * Is the receiver chain (`$owner->a->b`) rooted at one of the container params?
     *
     * @param  list<string>  $owners
     */
    private function rootedAtOwner(Node $receiver, array $owners): bool
    {
        return $this->chainRoot($receiver) instanceof Variable
            && is_string($this->chainRoot($receiver)->name)
            && in_array($this->chainRoot($receiver)->name, $owners, true);
    }

    /**
     * The variable a member-access chain bottoms out at (`$a->b->c()` → `$a`).
     */
    private function chainRoot(Node $node): Node
    {
        while ($node instanceof MethodCall || $node instanceof PropertyFetch
            || $node instanceof NullsafeMethodCall || $node instanceof NullsafePropertyFetch) {
            $node = $node->var;
        }

        return $node;
    }

    /**
     * The container variable that roots the unpack lookup's receiver chain.
     */
    private function unpackRoot(MethodCall $call): Variable
    {
        $root = $this->chainRoot($call->var);

        // Guaranteed a Variable here — rootedAtOwner already vetted the chain.
        return $root instanceof Variable ? $root : new Variable('');
    }

    /**
     * The local variable the lookup result is captured into — `$node = $owner->…($key)`,
     * including through an unwrap chain (`…->nodeById($id)->unwrapOrElse(…)`) — or null
     * when the result isn't assigned to a plain local (a bare statement or `return`).
     */
    private function capturedLocal(MethodCall $call): ?string
    {
        $node = $call;
        $parent = $node->getAttribute('parent');

        while (($parent instanceof MethodCall || $parent instanceof NullsafeMethodCall) && $parent->var === $node) {
            $node = $parent;
            $parent = $node->getAttribute('parent');
        }

        if ($parent instanceof Assign && $parent->expr === $node
            && $parent->var instanceof Variable && is_string($parent->var->name)) {
            return $parent->var->name;
        }

        return null;
    }

    /**
     * Is the container a PURE ENCAPSULATOR — used nowhere but the unpack? Every other
     * appearance must be a cheap `$owner->prop` read. A whole-object use (passed as an
     * argument or array element, a direct `$owner->method()` receiver, a comparison)
     * means the container is genuinely needed downstream — a co-subject, not packaging.
     */
    private function ownerIsPureEncapsulator(ClassMethod $method, Variable $unpackRoot, MethodCall $unpack): bool
    {
        $owner = $unpackRoot->name;

        foreach ((new NodeFinder)->findInstanceOf($method->stmts, Variable::class) as $variable) {
            if ($variable->name !== $owner || $variable === $unpackRoot) {
                continue;
            }

            $parent = $variable->getAttribute('parent');

            $isPropertyRead = ($parent instanceof PropertyFetch || $parent instanceof NullsafePropertyFetch)
                && $parent->var === $variable;

            if (! $isPropertyRead) {
                return false;
            }
        }

        return true;
    }
}
