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
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;

/**
 * Flag a reach-through `$edge->to->nodeId === …` that pulls a scalar out of an
 * intermediate value object to branch on it — WHEN the owning type already
 * exposes an intent method (`$edge->leaves($id)`). Law of Demeter / tell-
 * don't-ask: ask the owner, don't re-derive from its endpoints' raw fields.
 */
#[IntroducedIn('1.115.0')]
class DemeterEndpointReachProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    /** Intermediate accessors whose internals callers tend to reach through. */
    private const REACH_ACCESSORS = ['from', 'to', 'source', 'target', 'left', 'right'];

    /** Method-name prefixes that mark an owner as "ask me, don't reach in". */
    private const INTENT_PREFIXES = ['is', 'has', 'leaves', 'enters', 'contains', 'targets', 'connects', 'owns'];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Ask the owner with its intent method instead of reaching through $x->endpoint->field to branch (Law of Demeter)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A condition reaches through a project-owned object into an intermediate value object to compare a raw scalar — `$edge->to->nodeId === $id` — AND the owner already exposes an intent method (leaves()/enters()/is*()) that answers the same question.')
            ->leaveWhen('the owner is a vendor/framework type you cannot extend, no intent method exists on it yet (then the fix is to ADD one, a bigger change — judgment call), the access is a single hop, or it is a fluent builder chain.')
            ->whenUnsure('if the owner already has a method that answers this exact question, call it; if not, weigh adding one against leaving the reach-through — and absolve with a reason if adding a method is not worth it.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Law of Demeter: a method should talk to its immediate collaborators, not reach
through them into their parts. When code branches on `$edge->to->nodeId` it has
to know that an edge has a `to`, that a `to` has a `nodeId`, and how to compare
it — knowledge that belongs to the edge. If the edge already answers the
question (`$edge->leaves($id)`, `$edge->enters($id)`), the reach-through is a
re-derivation that drifts out of sync with the owner.

Bad — reach through the endpoint:
    if ($edge->to->nodeId === $nodeId && $edge->to->port === $port) {
        // ...
    }

Good — ask the edge:
    if ($edge->entersPort($nodeId, $port)) {
        // ...
    }

WHAT FIRES — a comparison (`===`/`!==`/`==`, or an `in_array(...)`) whose operand
is `$x->ACCESSOR->field`, where ACCESSOR is an endpoint-like accessor
(from/to/source/target/left/right), `$x` is a PARAMETER or `$this` property typed
as a PROJECT-OWNED class, and that owner class ALREADY declares an intent method
(is*/has*/leaves/enters/contains/targets/…). The existing intent method is the
gate: it proves the owner is meant to be asked.

WHAT DOES NOT — a single hop (`$edge->nodeId`); an owner that is a vendor type
(not in the index); an owner that has NO intent method yet (adding one is a
bigger call — left for a human); reach-throughs in plain data plumbing rather
than a branch; fluent builder chains.

A warning, not a sin — sometimes the right fix is to add a method to the owner,
which is a judgment call. Absolve with a reason when the reach-through is a
genuine one-off.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->index === null) {
            return $this->righteous();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $namespace = $this->getNamespace($ast);
        $uses = FileImports::of($ast);
        $warnings = [];
        $seen = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            $this->judgeClass($class, $namespace, $uses, $content, $warnings, $seen);
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  array<string, string>  $uses
     * @param  list<Warning>  $warnings
     * @param  array<string, true>  $seen
     */
    private function judgeClass(Node\Stmt\Class_ $class, ?string $namespace, array $uses, string $content, array &$warnings, array &$seen): void
    {
        if ($class->name === null) {
            return;
        }

        $ownFqcn = $namespace !== null && $namespace !== '' ? $namespace . '\\' . $class->name->toString() : $class->name->toString();
        $propertyOwners = $this->propertyOwners($class, $uses, $namespace, $ownFqcn);
        $classElements = $this->collectionElementTypes($class, $uses, $namespace, $ownFqcn);

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $paramOwners = $this->paramOwners($method, $uses, $namespace, $ownFqcn);
            // foreach ($this->edges as $edge) / foreach ($edges as $edge): bind the
            // loop variable to its collection's element type so reach-throughs
            // inside the loop resolve.
            $elements = $classElements + $this->paramCollectionElementTypes($method, $uses, $namespace, $ownFqcn);
            $owners = $this->foreachBoundOwners($method, $elements) + $paramOwners + $propertyOwners;

            foreach ($this->comparisonOperands($method->stmts) as $operand) {
                $reach = $this->endpointReach($operand, $owners);

                if ($reach === null) {
                    continue;
                }

                $intent = $this->ownerIntentMethod($reach['ownerFqcn']);

                if ($intent === null) {
                    continue;
                }

                $key = $reach['accessor'] . ':' . $reach['field'] . ':' . $method->name->toString();

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $warnings[] = $this->warningAt(
                    $operand->getStartLine(),
                    sprintf(
                        'Reaching through $%s->%s->%s to branch — %s already exposes intent methods (e.g. %s()); ask it (tell-don\'t-ask) instead of re-deriving from raw endpoint fields.',
                        $reach['root'],
                        $reach['accessor'],
                        $reach['field'],
                        $this->shortName($reach['ownerFqcn']),
                        $intent,
                    ),
                    $this->lineSnippet($content, $operand->getStartLine()),
                    'demeter-reach:' . $key,
                );
            }
        }
    }

    /**
     * Every operand of a comparison / in_array() within $stmts.
     *
     * @param  array<Node>  $stmts
     * @return list<Expr>
     */
    private function comparisonOperands(array $stmts): array
    {
        $finder = new NodeFinder;
        $operands = [];

        $comparisons = [
            Expr\BinaryOp\Identical::class,
            Expr\BinaryOp\NotIdentical::class,
            Expr\BinaryOp\Equal::class,
            Expr\BinaryOp\NotEqual::class,
        ];

        foreach ($comparisons as $type) {
            /** @var array<Expr\BinaryOp> $nodes */
            $nodes = $finder->findInstanceOf($stmts, $type);

            foreach ($nodes as $node) {
                $operands[] = $node->left;
                $operands[] = $node->right;
            }
        }

        foreach ($finder->findInstanceOf($stmts, Expr\FuncCall::class) as $call) {
            if ($call->name instanceof Node\Name && $call->name->toString() === 'in_array' && isset($call->args[0]) && $call->args[0] instanceof Node\Arg) {
                $operands[] = $call->args[0]->value;
            }
        }

        return $operands;
    }

    /**
     * When $expr is `$root->accessor->field` with `accessor` an endpoint and
     * `$root` an owned class, the reach description; else null.
     *
     * @param  array<string, string>  $owners
     * @return array{root: string, accessor: string, field: string, ownerFqcn: string}|null
     */
    private function endpointReach(Expr $expr, array $owners): ?array
    {
        if (! $expr instanceof Expr\PropertyFetch || ! $expr->name instanceof Node\Identifier) {
            return null;
        }

        $mid = $expr->var;

        if (! $mid instanceof Expr\PropertyFetch || ! $mid->name instanceof Node\Identifier) {
            return null;
        }

        $accessor = strtolower($mid->name->toString());

        if (! in_array($accessor, self::REACH_ACCESSORS, true)) {
            return null;
        }

        $root = $this->rootName($mid->var);

        if ($root === null || ! isset($owners[$root])) {
            return null;
        }

        return [
            'root' => $root,
            'accessor' => $mid->name->toString(),
            'field' => $expr->name->toString(),
            'ownerFqcn' => $owners[$root],
        ];
    }

    /**
     * A stable handle for the chain root: `$edge` -> "edge", `$this->edge` ->
     * "this->edge".
     */
    private function rootName(Expr $expr): ?string
    {
        if ($expr instanceof Expr\Variable && is_string($expr->name)) {
            return $expr->name;
        }

        if ($expr instanceof Expr\PropertyFetch
            && $expr->var instanceof Expr\Variable && $expr->var->name === 'this'
            && $expr->name instanceof Node\Identifier
        ) {
            return 'this->' . $expr->name->toString();
        }

        return null;
    }

    /**
     * An intent method on the owner FQCN (via the index), or null when the
     * owner is unknown or exposes none.
     */
    private function ownerIntentMethod(string $ownerFqcn): ?string
    {
        $summary = $this->index?->classByFqcn($ownerFqcn);

        if ($summary === null) {
            return null;
        }

        foreach (array_keys($summary->methods) as $name) {
            $lower = strtolower((string) $name);

            foreach (self::INTENT_PREFIXES as $prefix) {
                if (str_starts_with($lower, $prefix)) {
                    return (string) $name;
                }
            }
        }

        return null;
    }

    /**
     * Bind foreach loop variables to their collection's element type.
     *
     * @param  array<string, string>  $elements  source key ("this->edges" / "edges") => element FQCN
     * @return array<string, string>  loop var name => element FQCN
     */
    private function foreachBoundOwners(Node\Stmt\ClassMethod $method, array $elements): array
    {
        $owners = [];

        foreach ((new NodeFinder)->findInstanceOf($method->stmts ?? [], Node\Stmt\Foreach_::class) as $loop) {
            $source = $this->rootName($loop->expr);
            $var = $loop->valueVar instanceof Expr\Variable && is_string($loop->valueVar->name) ? $loop->valueVar->name : null;

            if ($source !== null && $var !== null && isset($elements[$source])) {
                $owners[$var] = $elements[$source];
            }
        }

        return $owners;
    }

    /**
     * Collection element types declared on the class — `@var Foo[]` / `@var
     * Collection<Foo>` properties and `@param DataCollection<int, Foo>` promoted
     * constructor params — keyed by their `$this->prop` source.
     *
     * @param  array<string, string>  $uses
     * @return array<string, string>  "this->prop" => element FQCN
     */
    private function collectionElementTypes(Node\Stmt\Class_ $class, array $uses, ?string $namespace, string $ownFqcn): array
    {
        $map = [];

        foreach ($class->getProperties() as $property) {
            $element = $this->elementOwner($this->docVarType($property->getDocComment()?->getText()), $uses, $namespace, $ownFqcn);

            foreach ($property->props as $prop) {
                if ($element !== null) {
                    $map['this->' . $prop->name->toString()] = $element;
                }
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            $doc = $ctor->getDocComment()?->getText();

            foreach ($ctor->params as $param) {
                if ($param->flags === 0 || ! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                    continue;
                }

                $element = $this->elementOwner($this->docParamType($doc, $param->var->name), $uses, $namespace, $ownFqcn);

                if ($element !== null) {
                    $map['this->' . $param->var->name] = $element;
                }
            }
        }

        return $map;
    }

    /**
     * Collection element types of the method's own params (`@param Foo[] $xs`),
     * keyed by the param name.
     *
     * @param  array<string, string>  $uses
     * @return array<string, string>  param name => element FQCN
     */
    private function paramCollectionElementTypes(Node\Stmt\ClassMethod $method, array $uses, ?string $namespace, string $ownFqcn): array
    {
        $doc = $method->getDocComment()?->getText();
        $map = [];

        foreach ($method->params as $param) {
            if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $element = $this->elementOwner($this->docParamType($doc, $param->var->name), $uses, $namespace, $ownFqcn);

            if ($element !== null) {
                $map[$param->var->name] = $element;
            }
        }

        return $map;
    }

    /**
     * The element type string of a `@var Type` tag (with balanced generics or a
     * trailing `[]`), or null.
     */
    private function docVarType(?string $doc): ?string
    {
        if ($doc === null || preg_match('/@var\s+([\\\\A-Za-z0-9_]+(?:<[^>]+>)?(?:\[\])?)/', $doc, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /**
     * The type string of a `@param Type $name` tag for the named param, or null.
     */
    private function docParamType(?string $doc, string $name): ?string
    {
        if ($doc === null || preg_match('/@param\s+([\\\\A-Za-z0-9_]+(?:<[^>]+>)?(?:\[\])?)\s+\$' . preg_quote($name, '/') . '\b/', $doc, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /**
     * Resolve a collection type string's element to an owned-class FQCN, else null.
     *
     * @param  array<string, string>  $uses
     */
    private function elementOwner(?string $typeStr, array $uses, ?string $namespace, string $ownFqcn): ?string
    {
        if ($typeStr === null) {
            return null;
        }

        $element = null;

        if (preg_match('/<([^>]+)>/', $typeStr, $m) === 1) {
            $parts = array_map('trim', explode(',', $m[1]));
            $element = end($parts) ?: null;
        } elseif (str_ends_with($typeStr, '[]')) {
            $element = substr($typeStr, 0, -2);
        }

        if ($element === null || $element === '') {
            return null;
        }

        $fqcn = $this->resolveName(ltrim($element, '\\'), $uses, $namespace);

        if ($fqcn === $ownFqcn || $this->index?->classByFqcn($fqcn) === null) {
            return null;
        }

        return $fqcn;
    }

    /**
     * @param  array<string, string>  $uses
     * @return array<string, string>  param name => owner FQCN (root form)
     */
    private function paramOwners(Node\Stmt\ClassMethod $method, array $uses, ?string $namespace, string $ownFqcn): array
    {
        $owners = [];

        foreach ($method->params as $param) {
            if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $fqcn = $this->ownedClassType($param->type, $uses, $namespace, $ownFqcn);

            if ($fqcn !== null) {
                $owners[$param->var->name] = $fqcn;
            }
        }

        return $owners;
    }

    /**
     * @param  array<string, string>  $uses
     * @return array<string, string>  "this->prop" => owner FQCN
     */
    private function propertyOwners(Node\Stmt\Class_ $class, array $uses, ?string $namespace, string $ownFqcn): array
    {
        $owners = [];

        foreach ($class->getProperties() as $property) {
            $fqcn = $this->ownedClassType($property->type, $uses, $namespace, $ownFqcn);

            foreach ($property->props as $prop) {
                if ($fqcn !== null) {
                    $owners['this->' . $prop->name->toString()] = $fqcn;
                }
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags !== 0 && $param->var instanceof Expr\Variable && is_string($param->var->name)) {
                    $fqcn = $this->ownedClassType($param->type, $uses, $namespace, $ownFqcn);

                    if ($fqcn !== null) {
                        $owners['this->' . $param->var->name] = $fqcn;
                    }
                }
            }
        }

        return $owners;
    }

    /**
     * Resolve a type node to a project-owned class FQCN that is in the index
     * and is not the declaring class; else null.
     *
     * @param  array<string, string>  $uses
     */
    private function ownedClassType(?Node $type, array $uses, ?string $namespace, string $ownFqcn): ?string
    {
        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if (! $type instanceof Node\Name) {
            return null;
        }

        $fqcn = $this->resolveName($type->toString(), $uses, $namespace);

        if ($fqcn === $ownFqcn || $this->index?->classByFqcn($fqcn) === null) {
            return null;
        }

        return $fqcn;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function resolveName(string $name, array $uses, ?string $namespace): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $head = explode('\\', $name)[0];

        if (isset($uses[$head])) {
            $rest = substr($name, strlen($head));

            return $uses[$head] . $rest;
        }

        return $namespace !== null && $namespace !== '' ? $namespace . '\\' . $name : $name;
    }

    /**
     * @return array<string, string>  alias => FQCN
     */
    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

}
