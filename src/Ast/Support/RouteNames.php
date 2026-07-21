<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\Node\FunctionLike;

/**
 * The route-name vocabulary of a codebase — every name a route registers, with its enclosing `group`
 * prefixes applied, plus the families a `resource` registration opens. Deliberately GENEROUS: the one
 * consumer asks the NEGATIVE question ("is this reference dangling?"), so a name that cannot be
 * resolved statically must never look unregistered.
 */
final class RouteNames
{
    use MemoisedPerCodebase;

    /**
     * @param  array<string, true>  $names     fully-qualified route name => true
     * @param  list<string>         $families  dotted prefixes opened by a resource registration ('users.')
     * @param  bool                 $dynamic   a `->name(<non-literal>)` was seen — names are built at runtime
     */
    private function __construct(
        private readonly array $names,
        private readonly array $families,
        private readonly bool $dynamic,
    ) {}

    /**
     * Did this codebase register ANY route name? False means the route files are outside the scan
     * scope, so nothing can be said about a reference — the caller must stay silent.
     */
    public function hasAny(): bool
    {
        return $this->names !== [];
    }

    /**
     * Was $name registered — exactly, under a resource family that mints it, or as the TAIL of a
     * group-prefixed name?
     *
     * The tail match is what makes this usable on a real app. A package registers its group prefix at
     * runtime (`Route::name($config->routeNamePrefix() . '.')->group($file)`), so the file's
     * `->name('docs')` is statically visible while the `workflows.` in front of it is not. A reference
     * is prefix + registered-name by construction, so matching the tail verifies the half that IS
     * knowable and stays blind to the half that isn't — `workflows.docs` resolves against `docs`, while
     * the renamed `workflows.hooks.intake.v2` still finds no registered `intake.v2` and is caught.
     *
     * A LEAF name composed at runtime (`->name($x)` on a route, not a group) is different: that name is
     * unknowable in any form, so the vocabulary is no longer closed and everything answers true.
     */
    public function isRegistered(string $name): bool
    {
        if ($this->dynamic || isset($this->names[$name])) {
            return true;
        }

        if (array_any($this->families, static fn (string $family): bool => str_starts_with($name, $family))) {
            return true;
        }

        return array_any(
            array_keys($this->names),
            static fn (string $registered): bool => str_ends_with($name, '.' . $registered),
        );
    }

    protected static function build(Codebase $codebase): static
    {
        $names = [];
        $families = [];
        $dynamic = false;
        $finder = new NodeFinder;

        foreach ($codebase->files() as $file) {
            foreach ($finder->find($file->ast, static fn (Node $n): bool => self::isRouteChainCall($n, 'name')) as $call) {
                // A `name()` that decorates a `group` is a PREFIX, not a name of its own; the names
                // inside the group already absorb it via their own prefix walk (and a prefix built at
                // runtime is survivable — the tail match in `isRegistered` sees past it).
                if (self::decoratesGroup($call)) {
                    continue;
                }

                $literal = self::literalArg($call);

                if ($literal !== null) {
                    $names[self::prefixOf($call) . $literal] = true;

                    continue;
                }

                // An INTERPOLATED name (`->name("mcp.oauth.resource.{$handle}")`) still states its
                // leading segments statically. Registering that head as a family keeps the rest of the
                // vocabulary checkable instead of surrendering the whole codebase to `$dynamic`.
                $prefix = self::literalPrefix($call);

                if ($prefix === null) {
                    $dynamic = true;

                    continue;
                }

                $families[] = self::prefixOf($call) . $prefix;
            }

            // A group's accumulated `as` IS a route name in its own right: Laravel hands it to any route
            // inside that declares no `->name()` of its own, so `['as' => '.commands']` under `shops.show`
            // makes `route('shops.show.commands')` resolve to the group's unnamed index route.
            foreach ($finder->find($file->ast, static fn (Node $n): bool => self::isGroupCall($n)) as $group) {
                $accumulated = rtrim(self::prefixOf($group) . self::groupPrefix($group), '.');

                if ($accumulated !== '') {
                    $names[$accumulated] = true;
                }
            }

            foreach ($finder->find($file->ast, static fn (Node $n): bool => self::isResourceRegistration($n)) as $call) {
                $literal = self::literalArg($call);

                if ($literal === null) {
                    $dynamic = true;

                    continue;
                }

                $families[] = self::prefixOf($call) . $literal . '.';
            }
        }

        return new self($names, $families, $dynamic);
    }

    /**
     * The dotted prefix every enclosing `group` contributes, outermost first. Walks up through the
     * closures a group nests, reading each group chain's `name('admin.')` or `['as' => 'admin.']`.
     */
    private static function prefixOf(Node $node): string
    {
        $segments = [];

        for ($current = $node; $current instanceof Node; $current = $current->getAttribute('parent')) {
            if (! $current instanceof FunctionLike) {
                continue;
            }

            $group = $current->getAttribute('parent');
            $group = $group instanceof Node && ! self::isGroupCall($group) ? $group->getAttribute('parent') : $group;

            if ($group instanceof Node && self::isGroupCall($group)) {
                $segments[] = self::groupPrefix($group);
            }
        }

        return implode('', array_reverse($segments));
    }

    /**
     * The name prefix a `group` call carries — from a `->name('admin.')` anywhere on its chain, or an
     * `['as' => 'admin.']` attributes array passed to it.
     */
    private static function groupPrefix(Node $group): string
    {
        foreach (($group->args ?? []) as $arg) {
            if (($arg->value ?? null) instanceof Array_ && ($as = self::arrayValue($arg->value, 'as')) !== null) {
                return $as;
            }
        }

        // Walk the whole chain, not just its method links: `Route::name('admin.')->group(…)` carries the
        // prefix on the STATIC call at the root, while `Route::prefix('x')->name('admin.')->group(…)`
        // carries it on a method link partway down.
        for ($link = $group; $link instanceof Node; $link = $link instanceof MethodCall ? $link->var : null) {
            if (self::callName($link) === 'name') {
                return self::literalArg($link) ?? '';
            }
        }

        return '';
    }

    /** Is this `name()` call decorating a `group` — i.e. the chain it heads is `…->name('x')->group(…)`? */
    private static function decoratesGroup(Node $call): bool
    {
        $parent = $call->getAttribute('parent');

        return $parent instanceof Node && self::isGroupCall($parent);
    }

    private static function isGroupCall(Node $node): bool
    {
        return self::callName($node) === 'group';
    }

    /**
     * Is this a `resource`/`apiResource` registration on a route chain — the shape that mints a whole
     * family of names from one call?
     */
    private static function isResourceRegistration(Node $node): bool
    {
        return in_array(self::callName($node), ['resource', 'apiResource'], true) && self::rootsAtRouter($node);
    }

    /**
     * Is this a call named $method that sits on a ROUTE chain — one rooted at the `Route` facade, or a
     * fluent chain that registers routes? The structural test keeps `$user->name('x')` out.
     */
    private static function isRouteChainCall(Node $node, string $method): bool
    {
        return self::callName($node) === $method && self::rootsAtRouter($node);
    }

    /**
     * Walk a fluent chain to its ROOT and demand it be the `Route` facade. Nothing softer works: method
     * names alone are hopeless signals here — `Publication::query()->resource($p)` and `$this->resource()`
     * are an Eloquent scope and a plain getter, and treating a `resource`/`name` link as proof of a router
     * mistook both for route registrations. The facade root is the one unambiguous marker.
     */
    private static function rootsAtRouter(Node $node): bool
    {
        for ($link = $node; $link instanceof MethodCall; $link = $link->var) {
            // walk to the root of the chain
        }

        if ($link instanceof StaticCall) {
            return $link->class instanceof Name
                && in_array(ltrim($link->class->toString(), '\\'), [LaravelNode::ROUTE, 'Route'], true);
        }

        return $link instanceof Variable && self::isRouterVariable($link);
    }

    /**
     * Is this variable a ROUTER by its declared TYPE — the `$router` a group closure is handed
     * (`->group(function (Router $router) { $router->get(…)->name(…); })`), which is how a large app
     * registers most of its routes. Resolved from the enclosing function's parameter type, never from
     * the variable being spelled "router".
     */
    private static function isRouterVariable(Variable $variable): bool
    {
        if (! is_string($variable->name)) {
            return false;
        }

        for ($scope = $variable; $scope instanceof Node; $scope = $scope->getAttribute('parent')) {
            if (! $scope instanceof FunctionLike) {
                continue;
            }

            foreach ($scope->getParams() as $param) {
                if (($param->var->name ?? null) === $variable->name) {
                    return in_array(TypeName::class($param->type), LaravelNode::ROUTER_TYPES, true);
                }
            }
        }

        return false;
    }

    /** The method/function name a call node invokes, or null. */
    private static function callName(Node $node): ?string
    {
        return ($node instanceof MethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier
            ? $node->name->toString()
            : null;
    }

    /** The first argument as a string literal, or null when it isn't one (dynamic). */
    private static function literalArg(Node $node): ?string
    {
        $first = ($node->args ?? [])[0]->value ?? null;

        return $first instanceof String_ ? $first->value : null;
    }

    /**
     * The STATIC leading text of an interpolated first argument (`"a.b.{$x}"` → `a.b.`), or null when
     * the argument isn't interpolated or begins with the interpolation (nothing static to anchor on).
     */
    private static function literalPrefix(Node $node): ?string
    {
        $first = ($node->args ?? [])[0]->value ?? null;

        if (! $first instanceof Encapsed || ! ($first->parts[0] ?? null) instanceof EncapsedStringPart) {
            return null;
        }

        $head = $first->parts[0]->value;

        return $head === '' ? null : $head;
    }

    /** The string value stored under $key in an array literal, or null. */
    private static function arrayValue(Array_ $array, string $key): ?string
    {
        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem
                && $item->key instanceof String_
                && $item->key->value === $key
                && $item->value instanceof String_
            ) {
                return $item->value->value;
            }
        }

        return null;
    }
}
