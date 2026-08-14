<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * Indirect feature envy: detects when a method uses an owned object's identity as a lookup key
 * to fetch data about it through a collaborator—the fact should be on the object.
 */
final class LookupEnvy
{
    use DetectsConstruction;

    private const int MAX_DEPTH = 10;

    private const array FACT_TYPES = ['bool', 'int', 'float', 'string', 'array', 'iterable'];

    /**
     * @param  array<string, true>  $ownedClasses
     */
    private function __construct(private readonly array $ownedClasses) {}

    public static function forCodebase(Codebase $codebase): self
    {
        $finder = new NodeFinder;
        $owned = [];

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, ClassLike::class) as $type) {
                $fqcn = ($type->namespacedName ?? null)?->toString();

                if ($fqcn !== null) {
                    $owned[$fqcn] = true;
                }
            }
        }

        return new self($owned);
    }

    /**
     * Is this method lookup envy — should it move onto the object it keys into?
     */
    public function isEnviedOwner(AstNode $match): bool
    {
        return $this->enviedOwner($match) !== null;
    }

    /**
     * The FQCN this method should be moved onto, or null when it isn't lookup envy.
     */
    public function enviedOwner(AstNode $match): ?string
    {
        $method = $match->node;
        $class = $match->enclosingClass();

        if (! $method instanceof ClassMethod || $method->stmts === null || $class === null) {
            return null;
        }

        if (! $this->returnsFact($method) || $this->constructs($method)) {
            return null;
        }

        $host = ($class->namespacedName ?? null)?->toString() ?? '';
        $param = $this->soleOwnedParam($method, $host);

        if ($param === null || ! $this->usedOnlyViaMembers($method, $param['name'])) {
            return null;
        }

        return $this->returnsAKeyedFetch($method, $param['name']) ? $param['type'] : null;
    }

    /**
     * A non-`$this` member access (`$x->m()` / `$x->p`) whose receiver expression
     * roots in `$this` and whose result is read — keyed by the param's member.
     */
    private function returnsAKeyedFetch(ClassMethod $method, string $param): bool
    {
        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($method->stmts, Return_::class) as $return) {
            if ($return->expr === null) {
                continue;
            }

            foreach ($finder->find([$return->expr], static fn (Node $node) => true) as $node) {
                if ($this->isKeyedFetchRead($node, $param)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isKeyedFetchRead(Node $node, string $param): bool
    {
        // $node reads a result: it's a member access whose receiver is a call.
        if (! $this->isMemberAccess($node)) {
            return false;
        }

        $producer = $node->var;

        if (! $producer instanceof MethodCall && ! $producer instanceof NullsafeMethodCall) {
            return false;
        }

        // The producing call is made ON a $this COLLABORATOR and is keyed by the param's member
        // (`$this->registry->get($node->key)`). A chain of the host's OWN methods
        // (`$this->find($ref->nodeId)->…`) is NOT envy — the method already lives on the owner of
        // the data it keys into, tell-dont-ask's prescribed home (#348).
        return $this->onCollaborator($producer)
            && $this->anyArgUsesParamMember($producer->args, $param)
            && $this->navigationDepth($node) <= self::MAX_DEPTH;
    }

    /**
     * @return array{name: string, type: string}|null
     */
    private function soleOwnedParam(ClassMethod $method, string $host): ?array
    {
        $found = null;

        foreach ($method->params as $param) {
            $type = TypeName::simpleName($param->type);

            if ($type === null || $type === $host || ! isset($this->ownedClasses[$type]) || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            if ($found !== null) {
                return null; // two owned params — not a single-subject lookup
            }

            $found = ['name' => $param->var->name, 'type' => $type];
        }

        return $found;
    }

    private function returnsFact(ClassMethod $method): bool
    {
        $type = TypeName::simpleName($method->returnType);

        return $type !== null && in_array(strtolower($type), self::FACT_TYPES, true);
    }

    /**
     * Is every use of $param a member access on it (`$param->x`)? A whole-param use
     * — passed as an argument, returned, assigned — means it's handed off, not used
     * as a key.
     */
    private function usedOnlyViaMembers(ClassMethod $method, string $param): bool
    {
        $used = false;

        foreach ((new NodeFinder)->findInstanceOf($method->stmts, Variable::class) as $variable) {
            if ($variable->name !== $param) {
                continue;
            }

            $used = true;
            $parent = $variable->getAttribute('parent');

            if (! $this->isMemberAccess($parent) || $parent->var !== $variable) {
                return false;
            }
        }

        return $used;
    }


    /**
     * @param  array<Node\Arg|Node\VariadicPlaceholder>  $args
     */
    private function anyArgUsesParamMember(array $args, string $param): bool
    {
        foreach ($args as $arg) {
            if ($arg instanceof Arg && $this->usesParamMember($arg->value, $param)) {
                return true;
            }
        }

        return false;
    }

    private function usesParamMember(Node $expr, string $param): bool
    {
        foreach ((new NodeFinder)->find([$expr], static fn (Node $node) => true) as $node) {
            if ($this->isMemberAccess($node) && $node->var instanceof Variable && $node->var->name === $param) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this call made ON a held collaborator — its receiver a property of `$this`
     * (`$this->registry->get(…)`), or a property chain down to one (`$this->workflow->graph->
     * nodeById(…)`)?
     *
     * The receiver must be PROPERTIES all the way to `$this`. A call on the RESULT of another call
     * is a link in a fluent chain (`->table(…)->join(…)->where(…)`), where every hop returns the
     * same builder and an argument is a binding, not a key into a store of facts about the object
     * (#479). And a call on `$this` itself is the host answering from its own state, which is the
     * owner speaking rather than envy (#348).
     */
    private function onCollaborator(Node $call): bool
    {
        $receiver = $call->var;

        if (! AstNode::isPropertyRead($receiver)) {
            return false;
        }

        while (AstNode::isPropertyRead($receiver)) {
            $receiver = $receiver->var;
        }

        return $receiver instanceof Variable && $receiver->name === 'this';
    }

    private function navigationDepth(Node $expr): int
    {
        $depth = 0;

        while ($this->isMemberAccess($expr) && $depth <= self::MAX_DEPTH) {
            $depth++;
            $expr = $expr->var;
        }

        return $depth;
    }

    private function isMemberAccess(?Node $node): bool
    {
        return $node instanceof PropertyFetch
            || $node instanceof NullsafePropertyFetch
            || $node instanceof MethodCall
            || $node instanceof NullsafeMethodCall;
    }

}
