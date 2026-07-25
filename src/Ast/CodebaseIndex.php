<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;

/**
 * The call graph: who calls what, cached per codebase and queried by receiver type.
 * Receivers are resolved conservatively; unresolved ones are never guessed.
 */
final class CodebaseIndex
{
    /**
     * method name => every call site of that name, across the tree. Null until
     * first built (lazily by {@see callsByName} or eagerly by {@see warm}).
     *
     * @var array<string, list<NodeMatch>>|null
     */
    private ?array $callsByName = null;

    public function __construct(private readonly Codebase $codebase) {}

    /**
     * Every call site of `$fqcn::$method(...)` — instance (`->$method()`, narrowed by the resolved
     * receiver type) and STATIC (`Class::$method()`, by the named class, with `self`/`static`
     * reading as the enclosing one). Both are call sites of the same declaration, so a caller
     * asking "who calls this?" gets the whole answer; a method reached only statically is not
     * uncalled.
     *
     * @return list<NodeMatch>
     */
    public function callersOf(string $fqcn, string $method): array
    {
        $callers = [];

        foreach ($this->callsByName()[$method] ?? [] as $call) {
            $receiver = $call->staticCallClass() ?? ReceiverResolver::typeOf($call);

            if ($receiver !== null && ($receiver === $fqcn || $this->codebase->extends($receiver, $fqcn))) {
                $callers[] = $call;
            }
        }

        return $callers;
    }

    /**
     * Build the call buckets now, so they exist before a fork and are inherited
     * (copy-on-write) instead of rebuilt in every worker. Returns $this to chain.
     */
    public function warm(): self
    {
        $this->callsByName();

        return $this;
    }

    /**
     * The lazily-built, then-cached map of method name => its call sites. One scan of every
     * `->method(...)` and `Class::method(...)` in the tree, bucketed by name — both spellings reach
     * the same declaration, so both belong in the graph.
     *
     * @return array<string, list<NodeMatch>>
     */
    private function callsByName(): array
    {
        if ($this->callsByName !== null) {
            return $this->callsByName;
        }

        $byName = [];

        foreach ([...$this->codebase->whereMethod()->get(), ...$this->codebase->whereStaticCall()->get()] as $call) {
            $name = $call->callName();

            if ($name !== null) {
                $byName[$name][] = $call;
            }
        }

        return $this->callsByName = $byName;
    }
}
