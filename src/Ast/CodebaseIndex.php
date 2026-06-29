<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;

/**
 * The call graph: who calls what. Built ONCE per {@see Codebase} (the instance is
 * cached by {@see Codebase::index()}) and used by detectors that must reason across
 * files — e.g. "this `?T` finder is de-nulled by all its callers".
 *
 * The cost is one pass over every `->method(...)` call in the tree, bucketed by
 * method name; after that each {@see callersOf} is a hash lookup plus a receiver
 * filter, not a fresh whole-tree scan. That matters at scale: a detector that asks
 * for the callers of hundreds of methods used to re-scan the codebase hundreds of
 * times. {@see warm} builds the buckets eagerly so a parent process can populate
 * them once and let forked workers share them copy-on-write.
 *
 * Receiver typing is delegated to {@see ReceiverResolver} (the same conservative
 * `$this` / typed-param / `$this->typedProperty` resolution the query engine uses);
 * an unresolved receiver is simply not a match — the graph never guesses.
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
     * Every call site `->$method(...)` whose receiver resolves to $fqcn (or a
     * subclass of it). The call sites are looked up by name from the prebuilt
     * buckets, then narrowed by the resolved receiver type.
     *
     * @return list<NodeMatch>
     */
    public function callersOf(string $fqcn, string $method): array
    {
        $callers = [];

        foreach ($this->callsByName()[$method] ?? [] as $call) {
            $receiver = ReceiverResolver::typeOf($call);

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
     * The lazily-built, then-cached map of method name => its call sites. One scan
     * of every `->method(...)` in the tree, bucketed by name.
     *
     * @return array<string, list<NodeMatch>>
     */
    private function callsByName(): array
    {
        if ($this->callsByName !== null) {
            return $this->callsByName;
        }

        $byName = [];

        foreach ($this->codebase->whereMethod()->get() as $call) {
            $name = $call->callName();

            if ($name !== null) {
                $byName[$name][] = $call;
            }
        }

        return $this->callsByName = $byName;
    }
}
