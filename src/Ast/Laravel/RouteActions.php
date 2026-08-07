<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Laravel;

use JesseGall\CodeCommandments\Ast\Support\MemoisedPerCodebase;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * Set of route actions (class::method entry points) — unions three signals: route-file registration, structural, response-reachable. Built once per codebase and memoised.
 */
final class RouteActions
{
    use MemoisedPerCodebase;

    /**
     * @param  array<string, true>  $actions     "Fqcn::method" => true — the union of all signals
     * @param  array<string, true>  $registered  "Fqcn::method" => true — bound in a route file only
     */
    private function __construct(private readonly array $actions, private readonly array $registered) {}

    /**
     * Is `$fqcn::$method` a route action by any of the three signals?
     */
    public function isAction(?string $fqcn, ?string $method): bool
    {
        return $fqcn !== null && $method !== null && isset($this->actions[self::key($fqcn, $method)]);
    }

    /**
     * Is `$fqcn::$method` bound in a ROUTE FILE — the ground-truth signal that it is a real routed
     * controller action (not merely a method that happens to take a request)? Requires the route files to
     * be in scan scope; the caller stays conservative (no proof → not registered) when they aren't.
     */
    public function isRegisteredAction(?string $fqcn, ?string $method): bool
    {
        return $fqcn !== null && $method !== null && isset($this->registered[self::key($fqcn, $method)]);
    }

    /**
     * The canonical action key for a class + method.
     */
    public static function key(string $fqcn, string $method): string
    {
        return ltrim($fqcn, '\\') . '::' . $method;
    }

    /**
     * Is this node a route registration — a call to a route verb (`get`/`post`/…) on the `Route` facade
     * or a `$router`, carrying an action argument?
     */
    public static function isRegistration(Node $node): bool
    {
        return self::verbOf($node) !== null && self::actionsOf($node) !== [];
    }

    /**
     * The route verb a registration call names (`get`, `post`, …), or null when it isn't one.
     */
    public static function verbOf(Node $node): ?string
    {
        $name = match (true) {
            $node instanceof MethodCall && $node->name instanceof Identifier => $node->name->toString(),
            $node instanceof StaticCall && $node->name instanceof Identifier => $node->name->toString(),
            default => null,
        };

        return $name !== null && in_array($name, LaravelNode::ROUTE_VERBS, true) ? $name : null;
    }

    /**
     * The action(s) a registration call binds — `[C::class, 'm']` → `C::m`, an invokable `C::class` →
     * `C::__invoke`. Reads the argument shapes only; an unrecognised argument yields nothing.
     *
     * @return list<string>
     */
    public static function actionsOf(Node $node): array
    {
        if (! isset($node->args) || ! is_array($node->args)) {
            return [];
        }

        $actions = [];

        foreach ($node->args as $arg) {
            $value = $arg->value ?? null;

            if ($value instanceof Array_ && ($action = self::arrayAction($value)) !== null) {
                $actions[] = $action;
            } elseif ($value instanceof ClassConstFetch && ($class = self::classConst($value)) !== null) {
                $actions[] = self::key($class, '__invoke');
            }
        }

        return $actions;
    }

    protected static function build(Codebase $codebase): static
    {
        $registered = [];
        $actions = [];
        $finder = new NodeFinder;
        $surface = ResponseSurface::forCodebase($codebase);

        foreach ($codebase->files() as $file) {
            foreach ($finder->find($file->ast, self::isRegistration(...)) as $registration) {
                foreach (self::actionsOf($registration) as $action) {
                    $registered[$action] = true;
                    $actions[$action] = true;
                }
            }

            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                $fqcn = ($class->namespacedName ?? null)?->toString();

                if ($fqcn === null) {
                    continue;
                }

                foreach ($class->getMethods() as $method) {
                    if ($method->isPublic() && self::isRequestHandler($method, $codebase, $surface, $finder)) {
                        $actions[self::key($fqcn, $method->name->toString())] = true;
                    }
                }
            }
        }

        return new self($actions, $registered);
    }

    /**
     * Does this public method look like a request handler — it takes an Illuminate request, renders a
     * page, or is typed to return a response-bound payload?
     */
    private static function isRequestHandler(ClassMethod $method, Codebase $codebase, ResponseSurface $surface, NodeFinder $finder): bool
    {
        foreach ($method->params as $param) {
            $type = TypeName::class($param->type);

            if ($type !== null && self::isRequestType($type, $codebase)) {
                return true;
            }
        }

        if ($surface->isResponseBound(TypeName::class($method->returnType))) {
            return true;
        }

        return $finder->findFirst($method->stmts ?? [], LaravelNode::rendersInertiaPage(...)) !== null;
    }

    private static function isRequestType(string $fqcn, Codebase $codebase): bool
    {
        foreach (LaravelNode::REQUEST_TYPES as $base) {
            if ($fqcn === $base || $codebase->extends($fqcn, $base)) {
                return true;
            }
        }

        return false;
    }


    /**
     * `[C::class, 'method']` → the `C::method` action key, or null when the array isn't that shape.
     */
    private static function arrayAction(Array_ $array): ?string
    {
        $items = array_values(array_filter($array->items, static fn ($item): bool => $item instanceof ArrayItem));

        if (count($items) < 2 || ! $items[1]->value instanceof String_ || ! $items[0]->value instanceof ClassConstFetch) {
            return null;
        }

        $class = self::classConst($items[0]->value);

        return $class === null ? null : self::key($class, $items[1]->value->value);
    }

    /**
     * The FQCN a `SomeClass::class` fetch names, or null when it isn't a `::class` on a resolvable name.
     */
    private static function classConst(ClassConstFetch $fetch): ?string
    {
        return $fetch->class instanceof Name
            && $fetch->name instanceof Identifier
            && $fetch->name->toString() === 'class'
            ? ltrim($fetch->class->toString(), '\\')
            : null;
    }
}
