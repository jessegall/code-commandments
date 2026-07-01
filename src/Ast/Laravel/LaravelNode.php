<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Laravel;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

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
