<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Laravel;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use JesseGall\CodeCommandments\Ast\Support\RouteActions;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;

/**
 * Laravel's (and Laravel MCP's) knowledge, as a node: the framework FQCNs live here once — the
 * facade namespace, the `ServiceProvider` base, the Eloquent `Model` and its cast contracts, the
 * HTTP/MCP request bases — and a detector reads `$n->isFacadeCall()` / `$n->receiverIsModel()`
 * instead of re-declaring the strings. Reached by type-hinting it in a `where` closure.
 */
final class LaravelNode extends NodeMatch
{
    /** The facade namespace: `Cache::`, `Log::`, `Mail::` all live under it. */
    public const string FACADE_NAMESPACE = 'Illuminate\\Support\\Facades\\';

    /** Wiring the framework at boot through facades is a provider's sanctioned job. */
    public const string SERVICE_PROVIDER = 'Illuminate\\Support\\ServiceProvider';

    /** The Eloquent model base. */
    public const string MODEL = 'Illuminate\\Database\\Eloquent\\Model';

    /** The HTTP request base. */
    public const string REQUEST = 'Illuminate\\Http\\Request';

    /** The form-request base — a validated request, whose `rules()` shape the framework dictates. */
    public const string FORM_REQUEST = 'Illuminate\\Foundation\\Http\\FormRequest';

    /** Laravel MCP's request. */
    public const string MCP_REQUEST = 'Laravel\\Mcp\\Request';

    /** Laravel MCP's tool base — a request-like handler whose `rules()`/`schema()` are contractual. */
    public const string MCP_TOOL = 'Laravel\\Mcp\\Server\\Tool';

    /** Inertia's entrypoint — `Inertia::render('Page', $props)` ships a payload to the frontend. */
    public const string INERTIA = 'Inertia\\Inertia';

    /** The `inertia(...)` helper — the function-call twin of `Inertia::render(...)`. */
    public const string INERTIA_HELPER = 'inertia';

    /**
     * Does $node render an Inertia page — `Inertia::render(...)` OR the `inertia(...)` helper? The one home
     * for "this expression ships a page to the frontend", shared by every analysis that scans for a page
     * response (the response surface, the route-action reader).
     */
    public static function rendersInertiaPage(Node $node): bool
    {
        if ($node instanceof StaticCall) {
            return $node->class instanceof Name
                && ltrim($node->class->toString(), '\\') === self::INERTIA
                && $node->name instanceof Identifier
                && $node->name->toString() === 'render';
        }

        return $node instanceof FuncCall
            && $node->name instanceof Name
            && ltrim($node->name->toString(), '\\') === self::INERTIA_HELPER;
    }

    /** The framework controller base — a class whose public actions return an HTTP response. */
    public const string CONTROLLER = 'Illuminate\\Routing\\Controller';

    /** The `Route` facade — `Route::get('/x', [C::class, 'm'])` registers a route action. */
    public const string ROUTE = 'Illuminate\\Support\\Facades\\Route';

    /** The route-registration verbs — the methods on `Route`/`$router` that bind a URL to an action. */
    public const array ROUTE_VERBS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'match', 'any'];

    /** The router types a route-group closure receives — the non-facade way routes are registered. */
    public const array ROUTER_TYPES = ['Illuminate\\Routing\\Router', 'Illuminate\\Contracts\\Routing\\Registrar'];

    /** The container methods that REGISTER an abstract — the wiring `OrphanedBindingDetector` audits. */
    public const array BINDING_METHODS = ['bind', 'bindIf', 'singleton', 'singletonIf', 'scoped', 'scopedIf', 'instance'];

    /** The `Event` facade — `Event::listen(X::class, L::class)` wires a listener to an event. */
    public const string EVENT = 'Illuminate\\Support\\Facades\\Event';

    /** The static calls that FIRE an event class — the demand side of `Event::listen`. */
    public const array EVENT_DISPATCHERS = ['dispatch', 'dispatchIf', 'dispatchUnless', 'broadcast'];

    /** The calls that look a route up BY NAME — the reference side of the route-name vocabulary. */
    public const array ROUTE_NAME_LOOKUPS = ['route', 'to_route', 'signedRoute', 'temporarySignedRoute'];

    /** Facades whose `::route()` builds a URL from a route NAME (as opposed to a request's `route($param)`). */
    public const array URL_GENERATORS = ['Illuminate\\Support\\Facades\\URL', 'URL', 'Illuminate\\Support\\Facades\\Redirect', 'Redirect'];

    /** Helpers returning a redirector/URL generator, so `redirect()->route('x')` is a name lookup. */
    public const array URL_HELPERS = ['redirect', 'url'];

    /** The HTTP/MCP request bases whose untyped reads are the smell. */
    public const array REQUEST_TYPES = [self::REQUEST, self::FORM_REQUEST, self::MCP_REQUEST];

    /** The Eloquent attribute-cast contracts — a cast has no container/DI, so it may use facades. */
    public const array CAST_CONTRACTS = [
        'Illuminate\\Contracts\\Database\\Eloquent\\CastsAttributes',
        'Illuminate\\Contracts\\Database\\Eloquent\\CastsInboundAttributes',
    ];

    /**
     * Is this a facade static call — `Cache::get(...)`, `Log::info(...)`? Matched by the framework's
     * facade namespace resolved from the file's imports, not a hand-kept list of facade names.
     */
    public function isFacadeCall(): bool
    {
        return $this->staticCallClassStartsWith(self::FACADE_NAMESPACE);
    }

    /**
     * The route name this node LOOKS UP as a string literal — `route('x')`, `to_route('x')`,
     * `redirect()->route('x')`, `URL::route('x')` — or null when it isn't a name lookup or the name is
     * built dynamically. The reference side of the route-name vocabulary {@see RouteNames} registers.
     */
    public function routeNameReference(): ?string
    {
        if (! $this->isRouteNameLookup()) {
            return null;
        }

        $first = $this->arguments()[0]->value ?? null;

        return $first instanceof String_ ? $first->value : null;
    }

    /**
     * Is this call a route-name LOOKUP rather than something else spelled `route`? The global helper
     * (`route('x')`, `to_route('x')`) always is. A `->route(…)` METHOD call is only a lookup on a
     * redirector or URL generator (`redirect()->route('x')`, `URL::route('x')`) — on a request it is
     * `Request::route($param)`, which fetches a route PARAMETER and shares nothing but the spelling.
     */
    private function isRouteNameLookup(): bool
    {
        if ($this->node instanceof FuncCall && $this->node->name instanceof Name) {
            return in_array($this->node->name->toString(), self::ROUTE_NAME_LOOKUPS, true);
        }

        if (! $this->node instanceof MethodCall && ! $this->node instanceof StaticCall) {
            return false;
        }

        if (! $this->node->name instanceof Identifier || ! in_array($this->node->name->toString(), self::ROUTE_NAME_LOOKUPS, true)) {
            return false;
        }

        $receiver = $this->node instanceof StaticCall ? $this->node->class : $this->node->var;

        return $receiver instanceof Name
            ? in_array(ltrim($receiver->toString(), '\\'), self::URL_GENERATORS, true)
            : $receiver instanceof FuncCall && $receiver->name instanceof Name && in_array($receiver->name->toString(), self::URL_HELPERS, true);
    }

    /**
     * The EVENT class this node wires a listener to — `Event::listen(X::class, L::class)` — or null
     * when it isn't a listener registration or the event is named dynamically (a variable holding a
     * discovered class, which nothing static can resolve).
     */
    public function listenedEventClass(): ?string
    {
        if (! $this->node instanceof StaticCall
            || ! $this->node->class instanceof Name
            || ! in_array(ltrim($this->node->class->toString(), '\\'), [self::EVENT, 'Event'], true)
            || ! $this->node->name instanceof Identifier
            || $this->node->name->toString() !== 'listen'
        ) {
            return null;
        }

        $first = $this->arguments()[0]->value ?? null;

        return $first instanceof ClassConstFetch && $first->class instanceof Name && $first->name instanceof Identifier
            && $first->name->toString() === 'class'
                ? ltrim($first->class->toString(), '\\')
                : null;
    }

    /**
     * Is this method declaration a ROUTE ACTION — an HTTP entry point, by any of the union signals
     * ({@see RouteActions})? Read on a `whereMethodDeclaration()` node (its own name is the action).
     */
    public function isRouteAction(): bool
    {
        return RouteActions::forCodebase($this->codebase)->isAction($this->enclosingClassName(), $this->enclosingFunctionName());
    }

    /**
     * Is this method a THIN pass-through that forwards to ANOTHER controller — its whole body a single
     * `return $this->other->action(...);` where `$this->other` is a different class whose method is a
     * ROUTE-FILE-REGISTERED action (a real routed controller)? That is a controller wrapping a controller:
     * a redundant entry point. Delegating into a domain SERVICE (a method that merely takes a request but
     * has no route of its own) is the correct shape and is NOT flagged — hence the registered-action gate.
     */
    public function delegatesToRouteAction(): bool
    {
        $call = $this->soleDelegationCall();

        if ($call === null || ! $call->name instanceof Identifier || ! $this->node instanceof ClassMethod) {
            return false;
        }

        $self = $this->enclosingClassName();
        $receiver = TypeResolver::forCodebase($this->codebase)->typeOf($call->var, $this->node, $self);

        if ($receiver === null || ltrim($receiver, '\\') === ltrim((string) $self, '\\')) {
            return false; // unresolved, or a self-call on the same controller
        }

        return RouteActions::forCodebase($this->codebase)->isRegisteredAction($receiver, $call->name->toString());
    }

    /**
     * The `Class::method` a THIN action delegates to — the resolved receiver type + method of its sole
     * forwarding call — or null when the method does more than forward or the receiver can't be typed. The
     * type-aware key two routes share when they are the same operation twice (both `return
     * $this->exporter->export(...)` onto the same `WorkflowExporter::export`), immune to a coincidental
     * property name.
     */
    public function thinDelegationTarget(): ?string
    {
        $call = $this->soleDelegationCall();

        if ($call === null || ! $call->name instanceof Identifier || ! $this->node instanceof ClassMethod) {
            return null;
        }

        $receiver = TypeResolver::forCodebase($this->codebase)->typeOf($call->var, $this->node, $this->enclosingClassName());

        return $receiver === null ? null : RouteActions::key($receiver, $call->name->toString());
    }

    /**
     * The single method call a thin pass-through method delegates through — `return $this->x->m(...);` or
     * `$this->x->m(...);` as the method's ONLY statement — or null when the method does more than forward.
     */
    private function soleDelegationCall(): ?MethodCall
    {
        if (! $this->node instanceof ClassMethod || count($this->node->stmts ?? []) !== 1) {
            return null;
        }

        $statement = $this->node->stmts[0];
        $expression = match (true) {
            $statement instanceof Return_ => $statement->expr,
            $statement instanceof Expression => $statement->expr,
            default => null,
        };

        return $expression instanceof MethodCall ? $expression : null;
    }

    /**
     * Is this call inside a `ServiceProvider`? Booting the framework through facades there is the
     * provider's job (and a `register()`/`boot()` has nothing to inject into).
     */
    public function inServiceProvider(): bool
    {
        return $this->codebase->extends($this->enclosingClassName(), self::SERVICE_PROVIDER);
    }

    /**
     * Is this call inside an Eloquent attribute cast? Eloquent `new`-instantiates a cast with no
     * container and no constructor DI, so it must reach services through facades — detected by the
     * cast contract the class implements, not a name.
     */
    public function isEloquentCast(): bool
    {
        foreach (self::CAST_CONTRACTS as $contract) {
            if ($this->codebase->implements($this->enclosingClassName(), $contract)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this call's receiver resolve to an Eloquent model — the tell for a set-then-`save()` /
     * mass-`update([...])` at a call site that belongs behind an intention-revealing model method?
     */
    public function receiverIsModel(): bool
    {
        $type = ReceiverResolver::typeOf($this);

        return $type !== null && $this->codebase->extends($type, self::MODEL);
    }

    /**
     * Is this `$x->update([...])` on something other than `$this` — a bare mass array-update on
     * another object (an Eloquent model at a call site, not the model's own intention method)?
     */
    public function isMassArrayUpdate(): bool
    {
        if (! $this->node instanceof MethodCall || ! $this->node->name instanceof Identifier || $this->node->name->toString() !== 'update') {
            return false;
        }

        if ($this->node->var instanceof Variable && $this->node->var->name === 'this') {
            return false;
        }

        $args = $this->arguments();

        return isset($args[0]) && $args[0]->value instanceof Array_;
    }
}
