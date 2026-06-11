<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;

/**
 * Find methods that hand-roll array-to-object hydration: reading several
 * statically-known keys out of an array (subscripts, Arr::get, data_get,
 * destructuring) and feeding them into an object instantiation.
 *
 * That is Spatie Laravel Data reimplemented by hand — `::from($row)` does
 * the mapping, coercion, and nested hydration automatically.
 *
 * A method is flagged when either:
 *  - it instantiates its own class (`new self/static/<OwnClass>`) and the
 *    method body reads >= min distinct known keys, or
 *  - it contains a `new <AnyClass>(...)` whose arguments themselves read
 *    >= min distinct known keys (hydrating someone else's DTO inline).
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindManualHydration implements Pipe
{
    /**
     * Arr:: methods that read a single key from an array.
     */
    private const ARR_READ_METHODS = ['get', 'pull', 'has', 'exists', 'add'];

    /**
     * Global helpers that read a single key/path from an array.
     */
    private const READ_FUNCTIONS = ['data_get'];

    private int $minKeyReads = 2;

    public function withMinKeyReads(int $min): self
    {
        $this->minKeyReads = max(1, $min);

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $nodeFinder = new NodeFinder;
        $matches = [];

        /** @var array<Node\Stmt\ClassLike> $classLikes */
        $classLikes = $nodeFinder->findInstanceOf($input->ast, Node\Stmt\ClassLike::class);

        foreach ($classLikes as $classLike) {
            $ownName = $classLike->name?->toString();

            foreach ($classLike->getMethods() as $method) {
                $hydration = $this->inspectMethod($method, $ownName, $input->useStatements);

                if ($hydration === null) {
                    continue;
                }

                $label = ($ownName !== null ? $ownName . '::' : '') . $method->name->toString() . '()';
                $line = $method->getStartLine();

                $matches[] = new MatchResult(
                    name: $method->name->toString(),
                    pattern: '',
                    match: $label,
                    line: $line,
                    offset: null,
                    content: $this->getSnippet($input->content, $line),
                    groups: [
                        'method' => $label,
                        'count' => (string) count($hydration),
                        'keys' => implode(', ', array_slice($hydration, 0, 5)),
                    ],
                );
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * Return the distinct keys read by hand when the method hydrates an
     * object from them, null when the method is clean.
     *
     * @param  array<string, string>  $useStatements
     * @return list<string>|null
     */
    private function inspectMethod(Node\Stmt\ClassMethod $method, ?string $ownName, array $useStatements): ?array
    {
        if ($method->stmts === null || $method->stmts === []) {
            return null;
        }

        $nodeFinder = new NodeFinder;

        /** @var array<Expr\New_> $instantiations */
        $instantiations = $nodeFinder->findInstanceOf($method->stmts, Expr\New_::class);

        if ($instantiations === []) {
            return null;
        }

        $methodKeys = $this->collectKeyReads($method->stmts, $useStatements);

        foreach ($instantiations as $new) {
            if ($this->instantiatesOwnClass($new, $ownName) && count($methodKeys) >= $this->minKeyReads) {
                return $methodKeys;
            }

            $argKeys = $this->collectKeyReads($this->argExpressions($new), $useStatements);

            if (count($argKeys) >= $this->minKeyReads) {
                return $argKeys;
            }
        }

        return null;
    }

    private function instantiatesOwnClass(Expr\New_ $new, ?string $ownName): bool
    {
        if (! $new->class instanceof Node\Name) {
            return false;
        }

        $name = $new->class->getLast();

        return $name === 'self'
            || $name === 'static'
            || ($ownName !== null && $name === $ownName);
    }

    /**
     * @return list<Node>
     */
    private function argExpressions(Expr\New_ $new): array
    {
        $exprs = [];

        foreach ($new->args as $arg) {
            if ($arg instanceof Node\Arg) {
                $exprs[] = $arg->value;
            }
        }

        return $exprs;
    }

    /**
     * Collect the distinct statically-known keys read anywhere in the
     * given subtree: literal subscripts, Arr::get-family calls, data_get
     * calls, and array-destructuring assignments.
     *
     * @param  array<Node>  $nodes
     * @param  array<string, string>  $useStatements
     * @return list<string>
     */
    private function collectKeyReads(array $nodes, array $useStatements): array
    {
        $nodeFinder = new NodeFinder;
        $keys = [];

        foreach ($nodeFinder->find($nodes, fn (Node $n): bool => true) as $node) {
            if ($node instanceof Expr\ArrayDimFetch && $node->dim !== null) {
                $key = $this->knownKey($node->dim);

                if ($key !== null) {
                    $keys[$key] = true;
                }

                continue;
            }

            if ($node instanceof Expr\StaticCall
                && $node->name instanceof Node\Identifier
                && in_array($node->name->toString(), self::ARR_READ_METHODS, true)
                && $this->isArrClass($node, $useStatements)
            ) {
                $key = $this->callKeyArg($node->args);

                if ($key !== null) {
                    $keys[$key] = true;
                }

                continue;
            }

            if ($node instanceof Expr\FuncCall
                && $node->name instanceof Node\Name
                && in_array($node->name->toString(), self::READ_FUNCTIONS, true)
            ) {
                $key = $this->callKeyArg($node->args);

                if ($key !== null) {
                    $keys[$key] = true;
                }

                continue;
            }

            if ($node instanceof Expr\Assign
                && ($node->var instanceof Expr\List_ || $node->var instanceof Expr\Array_)
            ) {
                foreach ($this->destructuredKeys($node->var) as $key) {
                    $keys[$key] = true;
                }

                continue;
            }

            if ($node instanceof Node\Stmt\Foreach_
                && ($node->valueVar instanceof Expr\List_ || $node->valueVar instanceof Expr\Array_)
            ) {
                foreach ($this->destructuredKeys($node->valueVar) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  array<Node\Arg|Node\VariadicPlaceholder>  $args
     */
    private function callKeyArg(array $args): ?string
    {
        if (count($args) < 2 || ! $args[1] instanceof Node\Arg) {
            return null;
        }

        return $this->knownKey($args[1]->value);
    }

    private function knownKey(Node $dim): ?string
    {
        if ($dim instanceof Scalar\String_) {
            return $dim->value;
        }

        if ($dim instanceof Expr\ClassConstFetch
            && $dim->class instanceof Node\Name
            && $dim->name instanceof Node\Identifier
        ) {
            return $dim->class->toString() . '::' . $dim->name->toString();
        }

        if ($dim instanceof Expr\PropertyFetch
            && $dim->name instanceof Node\Identifier
            && $dim->name->toString() === 'value'
            && $dim->var instanceof Expr\ClassConstFetch
        ) {
            return $this->knownKey($dim->var) . '->value';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function destructuredKeys(Expr\List_ | Expr\Array_ $pattern): array
    {
        $keys = [];

        foreach ($pattern->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $key = $this->knownKey($item->key);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, string>  $useStatements
     */
    private function isArrClass(Expr\StaticCall $call, array $useStatements): bool
    {
        if (! $call->class instanceof Node\Name) {
            return false;
        }

        $short = $call->class->getLast();
        $resolved = $useStatements[$short] ?? $call->class->toString();

        return $short === 'Arr'
            || $resolved === 'Arr'
            || str_ends_with($resolved, '\\Arr');
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
