<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Throwable;

/**
 * A codebase-wide census of method names whose CALL RESULT is used in a
 * null-distinguishing way — compared to null (`=== null`/`!== null`/`is_null`),
 * coalesced (`$x->m() ?? …`), or assigned to a local that is then null-tested.
 *
 * PreferEmptyOverNull (#89) uses this to honour its own "a caller must tell
 * ABSENT from EMPTY apart" LEAVE-WHEN: a `T | null` collection return whose
 * callers branch on the null genuinely distinguishes the two, so collapsing
 * `null → []` would change behaviour — suppress the finding. Keyed by method
 * SHORT name (cheap; an advisory errs toward not-nagging on name collisions).
 */
final class NullDistinguishedCallCensus
{
    /** @var array<string, array<string, true>> cacheKey => methodName => true */
    private static array $cache = [];

    /**
     * @return array<string, true>  method short names null-distinguished somewhere
     */
    public static function methodNames(CodebaseIndex $index): array
    {
        $files = [];

        foreach ($index->classes() as $summary) {
            $files[$summary->filePath] = true;
        }

        $paths = array_keys($files);
        sort($paths);
        $key = md5(implode('|', $paths));

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $names = [];
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;

        foreach ($paths as $file) {
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            try {
                $ast = $parser->parse($content);
            } catch (Throwable) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            self::collect($ast, $finder, $names);
        }

        return self::$cache[$key] = $names;
    }

    /**
     * @param  array<Node>  $ast
     * @param  array<string, true>  $names
     */
    private static function collect(array $ast, NodeFinder $finder, array &$names): void
    {
        // 1. Direct: `<call> === null` / `!== null`, `is_null(<call>)`, `<call> ?? …`.
        foreach ($finder->find($ast, static fn (Node $n): bool =>
            $n instanceof Expr\BinaryOp\Identical
            || $n instanceof Expr\BinaryOp\NotIdentical
            || $n instanceof Expr\BinaryOp\Equal
            || $n instanceof Expr\BinaryOp\NotEqual) as $cmp) {
            /** @var Expr\BinaryOp $cmp */
            foreach ([[$cmp->left, $cmp->right], [$cmp->right, $cmp->left]] as [$a, $b]) {
                if (self::isNullLiteral($b)) {
                    self::recordCall($a, $names);
                }
            }
        }

        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            if ($call->name instanceof Node\Name && strtolower($call->name->toString()) === 'is_null'
                && isset($call->args[0]) && $call->args[0] instanceof Node\Arg
            ) {
                self::recordCall($call->args[0]->value, $names);
            }
        }

        // Note: `??` is deliberately NOT a signal — `$x->m() ?? []` is the exact
        // null-guard the prophet wants to remove (null and [] collapse to []), so
        // treating it as "distinguishes" would suppress the true win. Only an
        // explicit branch on null (=== / !== / is_null) genuinely distinguishes.

        // 2. Assign-then-null-test: `$v = <call>; … if ($v === null) …`.
        foreach ($finder->findInstanceOf($ast, Expr\Assign::class) as $assign) {
            if (! $assign->var instanceof Expr\Variable || ! is_string($assign->var->name)) {
                continue;
            }

            $name = self::callName($assign->expr);

            if ($name === null) {
                continue;
            }

            $varName = $assign->var->name;

            // Any null-test of $varName anywhere in the file → distinguished.
            foreach ($finder->findInstanceOf($ast, Expr\Variable::class) as $var) {
                if ($var->name === $varName && self::isNullTested($var, $finder, $ast)) {
                    $names[$name] = true;
                    break;
                }
            }
        }
    }

    /**
     * @param  array<string, true>  $names
     */
    private static function recordCall(Node $node, array &$names): void
    {
        $name = self::callName($node);

        if ($name !== null) {
            $names[$name] = true;
        }
    }

    private static function callName(Node $node): ?string
    {
        if (($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\StaticCall)
            && $node->name instanceof Node\Identifier
        ) {
            return $node->name->toString();
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private static function isNullTested(Expr\Variable $var, NodeFinder $finder, array $ast): bool
    {
        foreach ($finder->find($ast, static fn (Node $n): bool =>
            $n instanceof Expr\BinaryOp\Identical
            || $n instanceof Expr\BinaryOp\NotIdentical
            || $n instanceof Expr\BinaryOp\Equal
            || $n instanceof Expr\BinaryOp\NotEqual) as $cmp) {
            /** @var Expr\BinaryOp $cmp */
            foreach ([[$cmp->left, $cmp->right], [$cmp->right, $cmp->left]] as [$a, $b]) {
                if ($a instanceof Expr\Variable && $a->name === $var->name && self::isNullLiteral($b)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isNullLiteral(Node $node): bool
    {
        return $node instanceof Expr\ConstFetch
            && $node->name instanceof Node\Name
            && strtolower($node->name->toString()) === 'null';
    }
}
