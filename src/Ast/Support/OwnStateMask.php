<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;

/**
 * Finds `$this->scratch?->call() ?? false` — a nullsafe reach into the object's OWN
 * nullable state, coalesced to a fake default. When that field is TRANSIENT (set
 * inside a method, not just injected), it's an invariant that should hold at the
 * call site; defaulting it manufactures a wrong answer (`isControlHandle()` quietly
 * returns false) for a state that can only be a bug. Resolve-or-throw instead — or,
 * better, stop storing per-call state on `$this` so the field is never null.
 *
 * The TRANSIENT gate is the precision: a constructor-injected optional collaborator
 * defaulted with `?? …` is a Null-Object choice, not a masked invariant.
 */
final class OwnStateMask
{
    /**
     * @param  array<string, array<string, true>>  $transientNullables  class FQCN => set of transient nullable property names
     */
    private function __construct(private readonly array $transientNullables) {}

    public static function forCodebase(Codebase $codebase): self
    {
        $finder = new NodeFinder;
        $transient = [];

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                $fqcn = ($class->namespacedName ?? null)?->toString();

                if ($fqcn !== null) {
                    $transient[$fqcn] = self::transientNullablesOf($class, $finder);
                }
            }
        }

        return new self($transient);
    }

    /**
     * Is this `?? <literal>` masking a transient own-state invariant?
     */
    public function masksOwnState(AstNode $match): bool
    {
        $node = $match->node;

        if (! $node instanceof Coalesce || ! $this->isNonNullLiteral($node->right)) {
            return false;
        }

        $property = $this->nullsafeOwnProperty($node->left);

        return $property !== null
            && isset($this->transientNullables[$match->enclosingClassName() ?? ''][$property]);
    }

    /**
     * The own property a `?->` short-circuits on within the coalesce's left —
     * `$this->prop?->call()`, `$this->a->prop?->x` — or null. The nullsafe sits on
     * the CALL/FETCH, so its receiver (`->var`) is `$this->prop`.
     */
    private function nullsafeOwnProperty(Node $expr): ?string
    {
        while ($expr instanceof MethodCall
            || $expr instanceof PropertyFetch
            || $expr instanceof NullsafeMethodCall
            || $expr instanceof NullsafePropertyFetch) {
            if ($expr instanceof NullsafeMethodCall || $expr instanceof NullsafePropertyFetch) {
                $property = self::ownProperty($expr->var);

                if ($property !== null) {
                    return $property;
                }
            }

            $expr = $expr->var;
        }

        return null;
    }

    /**
     * @return array<string, true>
     */
    private static function transientNullablesOf(Class_ $class, NodeFinder $finder): array
    {
        $nullable = [];

        foreach ($class->getProperties() as $property) {
            if ($property->isPrivate() && self::isNullableType($property->type)) {
                foreach ($property->props as $declared) {
                    $nullable[$declared->name->toString()] = true;
                }
            }
        }

        if ($nullable === []) {
            return [];
        }

        // Keep only those ASSIGNED outside the constructor — transient scratch state.
        $transient = [];

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null || strtolower($method->name->toString()) === '__construct') {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Assign::class) as $assign) {
                $property = self::ownProperty($assign->var);

                if ($property !== null && isset($nullable[$property])) {
                    $transient[$property] = true;
                }
            }
        }

        return $transient;
    }

    /**
     * The property name when $expr is `$this->NAME` (plain or nullsafe), else null.
     */
    private static function ownProperty(Node $expr): ?string
    {
        return ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch)
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
                ? $expr->name->toString()
                : null;
    }

    private function isNonNullLiteral(Node $expr): bool
    {
        if ($expr instanceof Scalar) {
            return true;
        }

        return $expr instanceof ConstFetch && in_array($expr->name->toLowerString(), ['true', 'false'], true);
    }

    private static function isNullableType(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            return true;
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $member) {
                if (($member instanceof Identifier || $member instanceof Name) && strtolower($member->toString()) === 'null') {
                    return true;
                }
            }
        }

        return false;
    }
}
