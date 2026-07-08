<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\VariadicPlaceholder;

/**
 * Fixes manual hydration loops by rewriting `array_map(fn => Foo::from(...))` to `Foo::collect()`
 * for passthrough callbacks only.
 */
final class ManualHydrationLoopScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            $this->collect($draft, $match);
        }

        return $draft->rewrites();
    }

    private function collect(Draft $draft, NodeMatch $match): void
    {
        $from = $match->node;

        if (! $from instanceof StaticCall || ! $from->class instanceof Name) {
            return;
        }

        $call = $this->mappingArrayMap($from);

        if ($call === null) {
            return;
        }

        $source = $match->file->source;
        $class = substr($source, $from->class->getStartFilePos(), $from->class->getEndFilePos() + 1 - $from->class->getStartFilePos());
        $items = substr($source, $call->args[1]->value->getStartFilePos(), $call->args[1]->value->getEndFilePos() + 1 - $call->args[1]->value->getStartFilePos());

        Writer::for($draft, $match)->replace($call, "{$class}::collect({$items})");
    }

    /**
     * The `array_map(callback, $items)` call this `from()` is the pure per-item mapper of —
     * but only when the callback maps each item STRAIGHT through `from()` (so `::collect()`
     * is exactly equivalent). Null otherwise.
     */
    private function mappingArrayMap(StaticCall $from): ?FuncCall
    {
        $parent = $from->getAttribute('parent');
        $callback = $from;

        // Arrow fn: `fn ($r) => Foo::from($r)` — exactly one param, passed verbatim.
        if ($parent instanceof ArrowFunction) {
            if (! $this->passesParamVerbatim($parent, $from)) {
                return null;
            }

            $callback = $parent;
            $parent = $parent->getAttribute('parent');
        } elseif (! $this->isFirstClassCallable($from)) {
            // Not an arrow fn → must be the first-class callable `Foo::from(...)`.
            return null;
        }

        // The callback must be array_map's FIRST argument, and array_map must be (callback, items).
        if (! $parent instanceof Arg) {
            return null;
        }

        $call = $parent->getAttribute('parent');

        if (! $call instanceof FuncCall
            || ! $call->name instanceof Name
            || $call->name->toString() !== 'array_map'
            || count($call->args) !== 2
            || $call->args[0] !== $parent
            || ! $call->args[1] instanceof Arg) {
            return null;
        }

        return $call;
    }

    private function passesParamVerbatim(ArrowFunction $arrow, StaticCall $from): bool
    {
        if (count($arrow->params) !== 1 || $arrow->expr !== $from || count($from->args) !== 1) {
            return false;
        }

        $param = $arrow->params[0]->var;
        $arg = $from->args[0];

        return $param instanceof Variable
            && is_string($param->name)
            && $arg instanceof Arg
            && $arg->value instanceof Variable
            && $arg->value->name === $param->name;
    }

    private function isFirstClassCallable(StaticCall $from): bool
    {
        return count($from->args) === 1 && $from->args[0] instanceof VariadicPlaceholder;
    }
}
