<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;

/**
 * The call graph: who calls what. Built once per {@see Codebase} and used by
 * detectors that must reason across files — e.g. "this `?T` finder is de-nulled
 * by all its callers".
 *
 * Receiver typing is delegated to {@see ReceiverResolver} (the same conservative
 * `$this` / typed-param / `$this->typedProperty` resolution the query engine uses);
 * an unresolved receiver is simply not a match — the graph never guesses.
 */
final class CodebaseIndex
{
    public function __construct(private readonly Codebase $codebase) {}

    /**
     * Every call site `->$method(...)` whose receiver resolves to $fqcn (or a
     * subclass of it). Calls are found through the engine's own `whereMethod`
     * selector, then narrowed by the resolved receiver type.
     *
     * @return list<NodeMatch>
     */
    public function callersOf(string $fqcn, string $method): array
    {
        $callers = [];

        foreach ($this->codebase->whereMethod($method)->get() as $call) {
            $receiver = ReceiverResolver::typeOf($call);

            if ($receiver !== null && ($receiver === $fqcn || $this->codebase->extends($receiver, $fqcn))) {
                $callers[] = $call;
            }
        }

        return $callers;
    }
}
