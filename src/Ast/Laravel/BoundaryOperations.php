<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Laravel;

use JesseGall\CodeCommandments\Ast\Support\Calls;
use JesseGall\CodeCommandments\Ast\Support\MemoisedPerCodebase;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * Every ENTRY POINT of the application, grouped by the KIND of boundary it sits on, with the domain
 * calls each one makes. Two entry points on different kinds that perform the same rare sequence of
 * domain calls are one operation wearing two faces — the shape {@see
 * \JesseGall\CodeCommandments\Detectors\Backend\Laravel\BoundaryDuplicatedOperationDetector} reports.
 */
final class BoundaryOperations
{
    use MemoisedPerCodebase;

    /**
     * A shared operation must involve at least this many domain calls to be one at all.
     */
    public const int MIN_SHARED_CALLS = 3;

    /**
     * How many entry points may use a call before it counts as INFRASTRUCTURE rather than an
     * operation. A pipeline runner or a query builder is reached from everywhere; the sequence that
     * IS an operation is reached from the handful of faces that expose it.
     */
    private const int INFRASTRUCTURE_THRESHOLD = 3;

    /**
     * @param  array<string, array{kind: string, calls: list<string>}>  $entries  "Fqcn::method" => its face and domain calls
     * @param  array<string, int>  $usage  domain call => how many entry points make it
     */
    private function __construct(private readonly array $entries, private readonly array $usage) {}

    /**
     * The entry points that share a rare domain-call sequence with `$fqcn::$method` ACROSS a different
     * kind of boundary. Empty when this method is not an entry point, or its work is its own.
     *
     * @return list<string>
     */
    public function twinsOf(?string $fqcn, ?string $method): array
    {
        $subject = $this->entries[$fqcn . '::' . $method] ?? null;

        if ($subject === null) {
            return [];
        }

        $twins = [];

        foreach ($this->entries as $id => $entry) {
            if ($entry['kind'] === $subject['kind']) {
                continue;
            }

            if (count($this->sharedOperation($subject['calls'], $entry['calls'])) >= self::MIN_SHARED_CALLS) {
                $twins[] = $id;
            }
        }

        return $twins;
    }

    /**
     * The domain calls two entry points have in common, minus anything used widely enough to be
     * infrastructure. What remains is work that exists for these faces alone.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private function sharedOperation(array $a, array $b): array
    {
        return array_values(array_filter(
            array_intersect($a, $b),
            fn (string $call): bool => ($this->usage[$call] ?? 0) <= self::INFRASTRUCTURE_THRESHOLD,
        ));
    }

    protected static function build(Codebase $codebase): static
    {
        $entries = [];
        $usage = [];
        $finder = new NodeFinder;
        $types = TypeResolver::forCodebase($codebase);
        $actions = RouteActions::forCodebase($codebase);

        foreach ($codebase->whereMethodDeclaration()->get() as $match) {
            $kind = self::kindOf($codebase, $actions, $match);

            if ($kind === null || ! $match->node instanceof ClassMethod || ! $match->node->isPublic()) {
                continue;
            }

            $calls = self::domainCalls($codebase, $types, $finder, $match);

            if (count($calls) < self::MIN_SHARED_CALLS) {
                continue;
            }

            $entries[$match->enclosingClassName() . '::' . $match->enclosingFunctionName()] = ['kind' => $kind, 'calls' => $calls];

            foreach ($calls as $call) {
                $usage[$call] = ($usage[$call] ?? 0) + 1;
            }
        }

        return new self($entries, $usage);
    }

    /**
     * Which KIND of boundary this method sits on — read from the class's base type and the route
     * table, never from a namespace segment. Null when it is not an entry point at all.
     */
    private static function kindOf(Codebase $codebase, RouteActions $actions, NodeMatch $match): ?string
    {
        $class = $match->enclosingClassName();

        foreach (LaravelNode::BOUNDARY_KINDS as $kind => $base) {
            if ($codebase->isA($class, $base)) {
                return $kind;
            }
        }

        return $actions->isAction($class, $match->enclosingFunctionName()) ? 'http' : null;
    }

    /**
     * The FIRST-PARTY calls this method makes on collaborators — `Type::method` for every call whose
     * receiver resolves to a class this codebase declares. Calls on `$this` are the method's own
     * scaffolding, and calls into vendor types are not the domain.
     *
     * @return list<string>
     */
    private static function domainCalls(Codebase $codebase, TypeResolver $types, NodeFinder $finder, NodeMatch $match): array
    {
        $calls = [];

        foreach ($finder->findInstanceOf($match->node->stmts ?? [], MethodCall::class) as $call) {
            if (! $call->name instanceof Identifier || ($call->var instanceof Variable && $call->var->name === 'this')) {
                continue;
            }

            $type = $types->typeOf($call->var, $match->node, $match->enclosingClassName());

            if ($type !== null && $codebase->declarationMatch($type) !== null) {
                $calls[$type . '::' . $call->name->toString()] = true;
            }
        }

        return array_keys($calls);
    }
}
