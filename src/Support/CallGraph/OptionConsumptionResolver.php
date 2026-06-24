<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

/**
 * Walks from a producer method to its CALL SITES and classifies how each caller
 * consumes the maybe-value — the cross-cutting signal that decides Option vs
 * nullable. The same analysis powers both directions: PreferOptionOverNull (a
 * nullable whose callers JUGGLE absence wants an Option) and NoOptionOveruse (an
 * Option whose callers all trivially UNWRAP it is ceremony).
 *
 * It re-parses only the caller files the {@see CodebaseIndex} points at, locates
 * the exact call by its byte offset, and reads the immediately-enclosing
 * expression (with one level of `$x = call()` then first-use tracing).
 */
final class OptionConsumptionResolver
{
    /** Option methods that ROUND-TRIP back to a bare value/null — the clearest ceremony tell. */
    private const UNWRAP = ['getorelse', 'getornull', 'getor', 'unwrap', 'valueor', 'tonullable'];

    /** `getOrThrow`-style — "I require presence". A legit use of Option's absence, NOT a round-trip. */
    private const REQUIRE = ['getorthrow', 'expect', 'orfail'];

    /** VALUE-ADDING Option methods — a transform pipeline nullsafe+coalesce can't express. The real reason to use Option. */
    private const CHAIN = ['map', 'flatmap', 'andthen', 'filter', 'each', 'tap', 'orelse', 'mapor'];

    /** Boolean QUERIES on an Option — ceremony equivalent to a null check, NOT value-adding. */
    private const QUERY = ['issome', 'isnone', 'isempty', 'ispresent', 'exists', 'contains', 'has', 'or', 'when'];

    /** @var array<string, list<Node>|null> parsed-and-parented caller files */
    private array $files = [];

    /**
     * How each caller of $fqcn::$method consumes the result. Kinds:
     *  - 'unwrap'   Option ->getOrElse/->getOrThrow/… (or nullable ?? default)
     *  - 'chain'    Option ->map/->filter/… (or a nullsafe ?-> on a nullable)
     *  - 'nullcheck' an explicit `=== null` / `if (! $x)` branch
     *  - 'passed'   handed to another call or returned as-is
     *  - 'other'    anything we could not classify
     *
     * @return list<string>
     */
    public function consumptions(string $fqcn, string $method, CodebaseIndex $index): array
    {
        $kinds = [];

        foreach ($index->callersOf($fqcn, $method) as $site) {
            $call = $this->locate($site);

            if ($call !== null) {
                $kinds[] = $this->classify($call);
            }
        }

        return $kinds;
    }

    private function locate(CallSite $site): ?Node
    {
        if ($site->startFilePos < 0) {
            return null;
        }

        $ast = $this->parse($site->callerFile);

        if ($ast === null) {
            return null;
        }

        // In a chain `a()->b()` the inner and outer calls share a startFilePos, so
        // also match the CALLEE name to pin the exact producer call (else we grab
        // the outer unwrap/chain and misread the consumption as the producer).
        $method = strtolower($site->calleeMethod);

        foreach ((new NodeFinder)->find($ast, static fn (Node $n): bool => $n->getStartFilePos() === $site->startFilePos
            && self::callName($n) === $method
        ) as $node) {
            return $node;
        }

        return null;
    }

    /** Classify a method invoked ON an Option result. */
    private function methodKind(string $m): string
    {
        if (in_array($m, self::UNWRAP, true)) {
            return 'unwrap';
        }

        if (in_array($m, self::REQUIRE, true)) {
            return 'require';
        }

        if (in_array($m, self::CHAIN, true)) {
            return 'chain';
        }

        return 'query'; // queries + any unknown method — ceremony, not value-adding
    }

    /** The lowercased callee name of a call node, or null. */
    private static function callName(Node $n): ?string
    {
        if (($n instanceof Expr\MethodCall || $n instanceof Expr\NullsafeMethodCall || $n instanceof Expr\StaticCall)
            && $n->name instanceof Node\Identifier
        ) {
            return strtolower($n->name->toString());
        }

        if ($n instanceof Expr\FuncCall && $n->name instanceof Node\Name) {
            return strtolower($n->name->getLast());
        }

        return null;
    }

    private function classify(Node $call): string
    {
        $parent = $call->getAttribute('parent');

        // call()->something(...) — the result is the receiver of another call.
        if (($parent instanceof Expr\MethodCall || $parent instanceof Expr\NullsafeMethodCall)
            && $parent->var === $call
            && $parent->name instanceof Node\Identifier
        ) {
            return $this->methodKind(strtolower($parent->name->toString()));
        }

        // call()?->x — a nullsafe reach is treating the nullable as a chain.
        if ($parent instanceof Expr\NullsafePropertyFetch && $parent->var === $call) {
            return 'chain';
        }

        // call() ?? default — coalesce to a default is the simplest unwrap.
        if ($parent instanceof Expr\BinaryOp\Coalesce && $parent->left === $call) {
            return 'unwrap';
        }

        // call() === null / !== null — an explicit absence branch.
        if (($parent instanceof Expr\BinaryOp\Identical || $parent instanceof Expr\BinaryOp\NotIdentical)
            && ($this->isNull($parent->left) || $this->isNull($parent->right))
        ) {
            return 'nullcheck';
        }

        // $x = call(); … — trace the first use of the assigned variable.
        if ($parent instanceof Expr\Assign && $parent->var instanceof Expr\Variable && is_string($parent->var->name)) {
            return $this->traceVariable($parent->var->name, $call);
        }

        // passed to another call, or returned directly.
        if ($parent instanceof Node\Arg || $parent instanceof Node\Stmt\Return_) {
            return 'passed';
        }

        return 'other';
    }

    /**
     * The first meaningful consumption of `$name` after its assignment, scanning
     * the enclosing function body.
     */
    private function traceVariable(string $name, Node $call): string
    {
        $fn = $this->enclosingFunction($call);

        if ($fn === null) {
            return 'other';
        }

        $assignLine = $call->getStartLine();

        foreach ((new NodeFinder)->find((array) $fn->getStmts(), static fn (Node $n): bool => true) as $node) {
            if ($node->getStartLine() <= $assignLine) {
                continue;
            }

            if ($node instanceof Expr\Variable && $node->name === $name) {
                $parent = $node->getAttribute('parent');

                if (($parent instanceof Expr\MethodCall || $parent instanceof Expr\NullsafeMethodCall) && $parent->var === $node && $parent->name instanceof Node\Identifier) {
                    return $this->methodKind(strtolower($parent->name->toString()));
                }

                if ($parent instanceof Expr\NullsafePropertyFetch && $parent->var === $node) {
                    return 'chain';
                }

                if ($parent instanceof Expr\BinaryOp\Coalesce && $parent->left === $node) {
                    return 'unwrap';
                }

                if (($parent instanceof Expr\BinaryOp\Identical || $parent instanceof Expr\BinaryOp\NotIdentical)
                    && ($this->isNull($parent->left) || $this->isNull($parent->right))
                ) {
                    return 'nullcheck';
                }

                if ($parent instanceof Node\Arg || $parent instanceof Node\Stmt\Return_) {
                    return 'passed';
                }
            }
        }

        return 'other';
    }

    private function enclosingFunction(Node $node): ?Node\FunctionLike
    {
        $cursor = $node->getAttribute('parent');

        while ($cursor instanceof Node) {
            if ($cursor instanceof Node\FunctionLike) {
                return $cursor;
            }

            $cursor = $cursor->getAttribute('parent');
        }

        return null;
    }

    private function isNull(?Node $node): bool
    {
        return $node instanceof Expr\ConstFetch && strtolower($node->name->toString()) === 'null';
    }

    /**
     * @return list<Node>|null
     */
    private function parse(string $file): ?array
    {
        if (array_key_exists($file, $this->files)) {
            return $this->files[$file];
        }

        $source = @file_get_contents($file);

        if ($source === false) {
            return $this->files[$file] = null;
        }

        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);

        if ($ast === null) {
            return $this->files[$file] = null;
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new ParentConnectingVisitor);
        $ast = $traverser->traverse($ast);

        return $this->files[$file] = $ast;
    }
}
