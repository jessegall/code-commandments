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

/**
 * Feature envy / misplaced domain query (tell-don't-ask). A method on class A
 * whose body QUERIES another project-owned object B's internal collection —
 * `Option::first($descriptor->outputs, …)`, `in_array($port,
 * $descriptor->handleNames())`, `array_filter($order->lines, …)` — is logic
 * living off its data. The query belongs ON B (`$descriptor->findOutput($port)`)
 * so it has one home and cannot drift across the classes that ask the same
 * question.
 *
 * Cross-file rule: B must be a project-owned class (present in the codebase
 * index) — a vendor/Eloquent type you cannot extend is exempt. Advisory: when
 * it fires, use judgment — move the query onto B, or absolve with a reason when
 * the class legitimately owns the algorithm.
 */
#[IntroducedIn('1.108.0')]
class FeatureEnvyProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    /**
     * Query function => the argument index that holds the COLLECTION (haystack),
     * so a foreign SCALAR passed as the needle (`in_array($foreign->name, …)`)
     * is not mistaken for the queried collection.
     */
    private const QUERY_FUNCTIONS = [
        'in_array' => 1,
        'array_search' => 1,
        'array_map' => 1,
        'array_filter' => 0,
        'array_reduce' => 0,
        'array_keys' => 0,
        'array_column' => 0,
        'array_find' => 0,
    ];

    private const QUERY_METHODS = [
        'first', 'firstwhere', 'filter', 'map', 'contains', 'search', 'reduce', 'where',
    ];

    private const SERIALIZER_METHODS = ['toarray', 'jsonserialize', '__tostring', '__construct'];

    /**
     * Reading a value through one of these is a serialization boundary, not a
     * reach into internals — `array_map(…, $bag->toArray())` processes the bag's
     * exported form, so it is not feature envy.
     */
    private const SERIALIZER_ACCESS = ['toarray', 'jsonserialize', 'tojson', '__tostring'];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Move a query over another object\'s internals onto that object (tell-don\'t-ask)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A method searches / filters / reduces over ANOTHER project-owned object\'s collection (its array property or an array-returning method) — the query is about that object, so it belongs ON it.')
            ->leaveWhen('the owner is a framework / vendor / Eloquent type you cannot extend; the deriving class legitimately OWNS the algorithm (a dedicated *Resolver/*Validator/*Visitor whose whole job is that multi-step computation); or it is a one-off, throwaway access.')
            ->whenUnsure('if the same question is asked over the same object in more than one place, move it onto the object; if this class is the only asker and the computation is genuinely its job, leave it.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Feature envy: a method that is more interested in another object's data than
its own. When a method on class A reaches into a project-owned object B and
runs a QUERY over B's internal collection, the logic is misplaced — it should
be a method ON B (tell-don't-ask), so every caller asks the same way and the
rule has a single home.

Bad — the query lives on the asker:
    // in ConnectCandidateResolver AND ConnectVerdictResolver (it drifts)
    private function findOutput(NodeDescriptor $descriptor, string $port): Option
    {
        return Option::first($descriptor->outputs, fn ($o) => $o->hasName($port));
    }
    private function isControlHandle(NodeDescriptor $descriptor, string $port): bool
    {
        return in_array($port, $descriptor->continuationHandleNames(), true)
            || in_array($port, $descriptor->bodyHandleNames(), true);
    }

Good — the query lives on its data:
    // on NodeDescriptor (the owner)
    public function findOutput(string $port): Option
    {
        return Option::first($this->outputs, fn ($o) => $o->hasName($port));
    }
    public function isControlHandle(string $port): bool { /* … over $this->… */ }

    // callers just ask:
    $descriptor->findOutput($port);
    $descriptor->isControlHandle($port);

WHAT FIRES — a method whose body runs a query (`Option::first`, `Arr::first`,
`array_filter`/`array_map`/`array_reduce`, `in_array`, or a `foreach`-accumulate)
over `$x->collection` / `$x->collectionMethod()` where `$x` is a PARAMETER or
`$this` PROPERTY typed as a project-owned class B (B is in the codebase index
and B is not the current class). Querying `$this`'s OWN collection is fine.

WHAT DOES NOT — querying your own data (`$this->outputs`); an owner that is a
vendor/framework type (not in the index, so it cannot be extended); a single
scalar read (`$b->name`); a query over a SERIALIZATION boundary
(`array_map(…, $bag->toArray())` / `jsonSerialize()` — the exported form, not
internals); a `*Data` DTO MAPPER (`array_map(fn => SocketData::from($s),
$d->sockets)` — moving it onto the domain owner would invert the dependency
onto the presentation type); and the case where THIS class is the rightful home
of the algorithm (a dedicated resolver/visitor). Those last ones are judgment
calls — absolve with a reason.

Distinct from DuplicateCode: this fires on a SINGLE misplaced query, before any
twin exists. The duplication is just the loudest instance of the same smell.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Cross-file rule: without the index we cannot tell a project-owned
        // class (extendable) from a vendor type (not), so stay silent.
        if ($this->index === null) {
            return $this->righteous();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ($this->namespaceScopes($ast) as [$namespace, $uses, $scope]) {
            foreach ((new NodeFinder)->findInstanceOf($scope, Node\Stmt\Class_::class) as $class) {
                $this->judgeClass($class, $namespace, $uses, $warnings);
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @param  array<string, string>  $uses
     * @param  list<Warning>  $warnings
     */
    private function judgeClass(Node\Stmt\Class_ $class, ?string $namespace, array $uses, array &$warnings): void
    {
        if ($class->name === null) {
            return;
        }

        $ownFqcn = $namespace !== null && $namespace !== ''
            ? $namespace . '\\' . $class->name->toString()
            : $class->name->toString();

        $propertyOwners = $this->propertyOwners($class, $uses, $namespace, $ownFqcn);

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null
                || in_array(strtolower($method->name->toString()), self::SERIALIZER_METHODS, true)
            ) {
                continue;
            }

            $paramOwners = $this->paramOwners($method, $uses, $namespace, $ownFqcn);

            if ($paramOwners === [] && $propertyOwners === []) {
                continue;
            }

            $envy = $this->firstForeignQuery($method, $paramOwners, $propertyOwners);

            if ($envy !== null) {
                $warnings[] = $this->warningAt(
                    $method->getStartLine(),
                    $this->messageFor($method->name->toString(), $envy['owner'], $envy['access']),
                    null,
                    'feature-envy:' . $this->shortName($envy['owner']) . ':' . $method->name->toString(),
                );
            }
        }
    }

    /**
     * Parameter name => owner FQCN, for params typed as a project-owned class
     * other than the declaring class.
     *
     * @param  array<string, string>  $uses
     * @return array<string, string>
     */
    private function paramOwners(Node\Stmt\ClassMethod $method, array $uses, ?string $namespace, string $ownFqcn): array
    {
        $owners = [];

        foreach ($method->params as $param) {
            if (! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $owner = $this->ownedClassType($param->type, $uses, $namespace, $ownFqcn);

            if ($owner !== null) {
                $owners[$param->var->name] = $owner;
            }
        }

        return $owners;
    }

    /**
     * Property name => owner FQCN, for `$this->prop` typed as a project-owned
     * class (incl. constructor-promoted properties).
     *
     * @param  array<string, string>  $uses
     * @return array<string, string>
     */
    private function propertyOwners(Node\Stmt\Class_ $class, array $uses, ?string $namespace, string $ownFqcn): array
    {
        $owners = [];

        foreach ($class->getProperties() as $property) {
            $owner = $this->ownedClassType($property->type, $uses, $namespace, $ownFqcn);

            if ($owner !== null) {
                foreach ($property->props as $prop) {
                    $owners[$prop->name->toString()] = $owner;
                }
            }
        }

        $constructor = $class->getMethod('__construct');

        if ($constructor !== null) {
            foreach ($constructor->params as $param) {
                if ($param->flags !== 0 && $param->var instanceof Expr\Variable && is_string($param->var->name)) {
                    $owner = $this->ownedClassType($param->type, $uses, $namespace, $ownFqcn);

                    if ($owner !== null) {
                        $owners[$param->var->name] = $owner;
                    }
                }
            }
        }

        return $owners;
    }

    /**
     * The FQCN of a type node when it names a project-owned class (present in
     * the index) other than the declaring class; otherwise null.
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

        $fqcn = $this->resolveFqcn($type, $uses, $namespace);

        if ($fqcn === $ownFqcn || $this->index?->classByFqcn($fqcn) === null) {
            return null;
        }

        return $fqcn;
    }

    /**
     * The first query in the method that ranges over a foreign owner's
     * collection, or null.
     *
     * @param  array<string, string>  $paramOwners
     * @param  array<string, string>  $propertyOwners
     * @return array{owner: string, access: string}|null
     */
    private function firstForeignQuery(Node\Stmt\ClassMethod $method, array $paramOwners, array $propertyOwners): ?array
    {
        $finder = new NodeFinder;

        // `array_filter($owner->coll, …)`, `in_array($x, $owner->coll, …)`, etc.
        // (A plain `foreach` over a collaborator's exposed collection is NOT
        // feature envy — that is normal iteration to do your own work. Envy is a
        // QUERY that DERIVES a value about the owner, so match those explicitly.)
        foreach ($finder->findInstanceOf($method->stmts, Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name) {
                continue;
            }

            $collectionArg = self::QUERY_FUNCTIONS[strtolower($call->name->getLast())] ?? null;

            if ($collectionArg === null || ! isset($call->args[$collectionArg]) || ! $call->args[$collectionArg] instanceof Node\Arg) {
                continue;
            }

            $owner = $this->ownerOfCollectionAccess($call->args[$collectionArg]->value, $paramOwners, $propertyOwners);

            if ($owner === null) {
                continue;
            }

            // `array_map(fn ($x) => SomeData::from($x), $owner->coll)` is a DTO
            // MAPPER, not feature envy — the map produces a presentation type, so
            // it belongs on the mapper, not the (domain) owner. Exempt.
            if (strtolower($call->name->getLast()) === 'array_map'
                && $call->args[0] instanceof Node\Arg
                && $this->mapsToDto($call->args[0]->value)
            ) {
                continue;
            }

            return ['owner' => $owner, 'access' => $this->describeAccess($call->args[$collectionArg]->value)];
        }

        // `Option::first($owner->coll, …)`, `Arr::first($owner->coll, …)`, etc.
        foreach ($finder->findInstanceOf($method->stmts, Expr\StaticCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array(strtolower($call->name->toString()), self::QUERY_METHODS, true)
                || $call->args === []
                || ! $call->args[0] instanceof Node\Arg
            ) {
                continue;
            }

            $owner = $this->ownerOfCollectionAccess($call->args[0]->value, $paramOwners, $propertyOwners);

            if ($owner !== null) {
                return ['owner' => $owner, 'access' => $this->describeAccess($call->args[0]->value)];
            }
        }

        return null;
    }

    /**
     * Whether a map callback's result is a `*Data` DTO construction — a
     * static `SomeData::from(...)`/`SomeData::collect(...)` or `new SomeData(...)`.
     * Such an `array_map` is a presentation mapper, not feature envy.
     */
    private function mapsToDto(Expr $callback): bool
    {
        $result = match (true) {
            $callback instanceof Expr\ArrowFunction => $callback->expr,
            $callback instanceof Expr\Closure => $this->closureReturn($callback),
            default => null,
        };

        if ($result instanceof Expr\StaticCall && $result->class instanceof Node\Name) {
            return str_ends_with($result->class->getLast(), 'Data');
        }

        if ($result instanceof Expr\New_ && $result->class instanceof Node\Name) {
            return str_ends_with($result->class->getLast(), 'Data');
        }

        return false;
    }

    private function closureReturn(Expr\Closure $closure): ?Expr
    {
        foreach ($closure->stmts as $statement) {
            if ($statement instanceof Node\Stmt\Return_) {
                return $statement->expr;
            }
        }

        return null;
    }

    /**
     * When $expr is `$owner->member` or `$owner->member()` and `$owner` is a
     * foreign project-owned subject, the owner's FQCN; otherwise null.
     *
     * @param  array<string, string>  $paramOwners
     * @param  array<string, string>  $propertyOwners
     */
    private function ownerOfCollectionAccess(Expr $expr, array $paramOwners, array $propertyOwners): ?string
    {
        if (! $expr instanceof Expr\PropertyFetch && ! $expr instanceof Expr\MethodCall) {
            return null;
        }

        // Reading through a serialization boundary (`$x->toArray()`,
        // `$x->jsonSerialize()`) is not a reach into internals — exempt.
        if ($expr instanceof Expr\MethodCall
            && $expr->name instanceof Node\Identifier
            && in_array(strtolower($expr->name->toString()), self::SERIALIZER_ACCESS, true)
        ) {
            return null;
        }

        $root = $expr->var;

        // `$param->member`
        if ($root instanceof Expr\Variable && is_string($root->name)) {
            return $paramOwners[$root->name] ?? null;
        }

        // `$this->prop->member`
        if ($root instanceof Expr\PropertyFetch
            && $root->var instanceof Expr\Variable
            && $root->var->name === 'this'
            && $root->name instanceof Node\Identifier
        ) {
            return $propertyOwners[$root->name->toString()] ?? null;
        }

        return null;
    }

    private function describeAccess(Expr $expr): string
    {
        if ($expr instanceof Expr\PropertyFetch && $expr->name instanceof Node\Identifier) {
            return '$' . $this->rootName($expr->var) . '->' . $expr->name->toString();
        }

        if ($expr instanceof Expr\MethodCall && $expr->name instanceof Node\Identifier) {
            return '$' . $this->rootName($expr->var) . '->' . $expr->name->toString() . '()';
        }

        return 'the collection';
    }

    private function rootName(Expr $var): string
    {
        if ($var instanceof Expr\Variable && is_string($var->name)) {
            return $var->name;
        }

        if ($var instanceof Expr\PropertyFetch
            && $var->var instanceof Expr\Variable
            && $var->var->name === 'this'
            && $var->name instanceof Node\Identifier
        ) {
            return 'this->' . $var->name->toString();
        }

        return 'x';
    }

    private function messageFor(string $method, string $owner, string $access): string
    {
        return sprintf(
            '%s() queries %s\'s internals (%s) — that question belongs ON %s (tell-don\'t-ask). Move it to a method on %s (e.g. `$%s->%s(...)`) and have this call site delegate, so the query has one home and cannot drift.',
            $method,
            $this->shortName($owner),
            $access,
            $this->shortName($owner),
            $this->shortName($owner),
            lcfirst($this->shortName($owner)),
            $method,
        );
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }

    /**
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: array<string, string>, 2: array<Node>}>
     */
    private function namespaceScopes(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            $namespace = null;
            $scope = [$node];

            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name?->toString();
                $scope = $node->stmts;
            }

            $out[] = [$namespace, $this->collectUses($scope), $scope];
        }

        return $out;
    }

    /**
     * @param  array<Node>  $stmts
     * @return array<string, string>
     */
    private function collectUses(array $stmts): array
    {
        $uses = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                continue;
            }

            foreach ($stmt->uses as $useUse) {
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$alias] = $useUse->name->toString();
            }
        }

        return $uses;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function resolveFqcn(Node\Name $name, array $uses, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $parts = explode('\\', $name->toString());
        $first = $parts[0];

        if (isset($uses[$first])) {
            $parts[0] = $uses[$first];

            return implode('\\', $parts);
        }

        if ($namespace !== null && $namespace !== '' && ! in_array($first, ['self', 'static', 'parent'], true)) {
            return $namespace . '\\' . $name->toString();
        }

        return $name->toString();
    }
}
