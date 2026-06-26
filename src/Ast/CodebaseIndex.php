<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * The call graph: who calls what, and the types that flow into a call's receiver.
 * Built once per {@see Codebase} and used by detectors that must reason across
 * files — e.g. "this `?T` finder is de-nulled by all its callers".
 *
 * Receiver typing is delegated to {@see ReceiverResolver} (the same conservative
 * `$this` / typed-param / `$this->typedProperty` resolution the query engine uses);
 * an unresolved receiver is simply not a match — the graph never guesses.
 */
final class CodebaseIndex
{
    public function __construct(private readonly Codebase $codebase) {}

    /**
     * Every method whose return type is a nullable class (`?C` / `C | null`) — a
     * finder that resolves to a value-or-null.
     *
     * @return list<NodeMatch>
     */
    public function nullableObjectFinders(): array
    {
        $finders = [];
        $finder = new NodeFinder;

        foreach ($this->codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, ClassMethod::class) as $method) {
                /** @var ClassMethod $method */
                if (TypeName::nullableClass($method->returnType) !== null) {
                    $finders[] = new NodeMatch($method, $file);
                }
            }
        }

        return $finders;
    }

    /**
     * Every call site `->$method(...)` whose receiver resolves to $fqcn (or a
     * subclass of it).
     *
     * @return list<NodeMatch>
     */
    public function callersOf(string $fqcn, string $method): array
    {
        $callers = [];
        $finder = new NodeFinder;

        foreach ($this->codebase->files() as $file) {
            $calls = $finder->find($file->ast, static fn (Node $node): bool =>
                ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
                && $node->name instanceof Identifier
                && $node->name->toString() === $method);

            foreach ($calls as $call) {
                $receiver = ReceiverResolver::typeOf(new NodeMatch($call, $file));

                if ($receiver !== null && ($receiver === $fqcn || $this->codebase->extends($receiver, $fqcn))) {
                    $callers[] = new NodeMatch($call, $file);
                }
            }
        }

        return $callers;
    }
}
