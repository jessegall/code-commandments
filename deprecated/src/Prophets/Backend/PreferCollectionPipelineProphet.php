<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag a nested composition of `array_*` higher-order calls —
 * `array_values(array_map(…, array_filter(…)))` — which reads inside-out. A
 * Laravel Collection chain (`collect($x)->filter(…)->map(…)->values()->all()`)
 * expresses the same pipeline top-to-bottom, in execution order.
 *
 * Advisory only: a single un-nested `array_map`/`array_filter` reads fine, and
 * the exact terminal (`->all()` vs `->toArray()`) / list-ness is a judgment call,
 * so the chain is suggested, not auto-applied.
 */
#[IntroducedIn('1.143.0')]
class PreferCollectionPipelineProphet extends PhpCommandment
{
    /** array_* higher-order / transform functions that map onto Collection methods. */
    private const ARRAY_PIPELINE_FUNCTIONS = [
        'array_map' => 'map',
        'array_filter' => 'filter',
        'array_values' => 'values',
        'array_keys' => 'keys',
        'array_reduce' => 'reduce',
        'array_unique' => 'unique',
        'array_merge' => 'merge',
        'array_reverse' => 'reverse',
        'array_slice' => 'slice',
        'array_flip' => 'flip',
        'array_combine' => 'combine',
    ];

    public function description(): string
    {
        return 'Prefer a Collection chain over nested array_* compositions (they read inside-out)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('Two or more `array_*` higher-order calls are nested — one is the argument of another (`array_values(array_map(...))`, `array_map(..., array_filter(...))`). It reads inside-out: the innermost runs first.')
            ->leaveWhen('a SINGLE un-nested `array_map`/`array_filter` (no composition) — that reads fine inline, and a Collection wrapper would be ceremony. Also leave a genuinely hot path where the Collection allocation matters and the team has marked it.')
            ->whenUnsure('rewrite the nest as `collect($x)->filter(...)->map(...)->values()->all()` — each stage on its own line, in execution order. Keep `->values()` where the original had `array_values(...)` to preserve list-ness.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Nested `array_*` calls read inside-out — the filter that runs FIRST is written
LAST, buried in the innermost parentheses. A Collection chain expresses the same
pipeline top-to-bottom, in the order it executes, naming each stage.

Bad — nested, reads inside-out:
    return array_values(array_map(
        static fn (mixed $row): Spec => Spec::forArray($row),
        array_filter($rows, static fn (mixed $row): bool => is_array($row)),
    ));

Good — fluent, reads in execution order:
    return collect($rows)
        ->filter(static fn (mixed $row): bool => is_array($row))
        ->map(static fn (mixed $row): Spec => Spec::forArray($row))
        ->values()
        ->all();

Mapping: array_map→map, array_filter→filter, array_values→values, array_keys→keys,
array_reduce→reduce, array_unique→unique, array_merge→merge, … Terminate with
`->all()` (or `->toArray()`) since the original returned an array; keep `->values()`
where the original had `array_values(...)`.

WHAT FIRES — a composition of TWO OR MORE `array_*` pipeline calls where one is an
argument of another. The deeper the nest, the stronger the signal.

WHAT DOES NOT — a single un-nested `array_map`/`array_filter` (reads fine inline);
an `array_*` call whose arguments are not themselves `array_*` pipeline calls.
Advisory: the rewrite (and its terminal) is a readability judgment.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;

        /** @var list<Expr\FuncCall> $pipelineCalls */
        $pipelineCalls = [];

        foreach ($finder->findInstanceOf($ast, Expr\FuncCall::class) as $call) {
            if ($this->pipelineName($call) !== null) {
                $pipelineCalls[] = $call;
            }
        }

        // A call is "inner" if it is a direct argument of another pipeline call —
        // those are part of a composition we report at its OUTERMOST node only.
        $innerIds = [];

        foreach ($pipelineCalls as $call) {
            foreach ($this->pipelineArgCalls($call) as $argCall) {
                $innerIds[spl_object_id($argCall)] = true;
            }
        }

        $warnings = [];

        foreach ($pipelineCalls as $call) {
            // Report only the composition root: it nests a pipeline call AND is
            // not itself nested inside another pipeline call.
            if (isset($innerIds[spl_object_id($call)]) || $this->pipelineArgCalls($call) === []) {
                continue;
            }

            // A composition containing an `array_filter(..., <type-narrowing
            // predicate>)` (e.g. `is_array(...)`) must stay procedural: array_filter
            // with a type-guard narrows the element type in static analysis
            // (`list<mixed>` → `list<array>`), but `Collection::filter()` does NOT
            // narrow — the chain would widen the result back and break the declared
            // `list<T>` return (#146). Leave it.
            if ($this->hasTypeNarrowingFilter($call)) {
                continue;
            }

            $line = $call->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                sprintf('Nested `%s(...)` composition reads inside-out — prefer a Collection chain `collect($x)->...->all()` that reads top-to-bottom in execution order (array_map→map, array_filter→filter, array_values→values, …).', $this->pipelineName($call)),
                $this->lineSnippet($content, $line),
                'array-pipeline-nest',
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Whether the composition contains an `array_filter(..., P)` whose predicate P
     * is a TYPE-NARROWING callable — a first-class/string `is_array`/`is_string`/…
     * reference, or a closure/arrow whose result is an `is_*()` check or an
     * `instanceof`. Such a filter narrows the element type for static analysis;
     * `Collection::filter()` does not, so the chain rewrite is unsafe (#146).
     */
    private function hasTypeNarrowingFilter(Expr\FuncCall $root): bool
    {
        foreach ((new NodeFinder)->findInstanceOf($root, Expr\FuncCall::class) as $fc) {
            if (! $fc->name instanceof Node\Name || strtolower($fc->name->toString()) !== 'array_filter') {
                continue;
            }

            $args = $fc->getArgs();

            if (count($args) >= 2 && $this->isTypeNarrowingPredicate($args[1]->value)) {
                return true;
            }
        }

        return false;
    }

    private function isTypeNarrowingPredicate(Expr $predicate): bool
    {
        if ($predicate instanceof Expr\FuncCall && $predicate->name instanceof Node\Name) {
            return $this->isNarrowingFunction($predicate->name->toString());
        }

        if ($predicate instanceof Node\Scalar\String_) {
            return $this->isNarrowingFunction($predicate->value);
        }

        if ($predicate instanceof Expr\ArrowFunction) {
            return $this->exprIsTypeCheck($predicate->expr);
        }

        if ($predicate instanceof Expr\Closure) {
            foreach ((new NodeFinder)->findInstanceOf($predicate->stmts, Node\Stmt\Return_::class) as $return) {
                if ($return->expr !== null && $this->exprIsTypeCheck($return->expr)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function exprIsTypeCheck(Expr $expr): bool
    {
        // A type-guard CONJUNCT narrows too: `is_string($e) && $e !== ''` narrows
        // $e to string in static analysis (PHPStan narrows on `&&`, not `||`).
        if ($expr instanceof Expr\BinaryOp\BooleanAnd || $expr instanceof Expr\BinaryOp\LogicalAnd) {
            return $this->exprIsTypeCheck($expr->left) || $this->exprIsTypeCheck($expr->right);
        }

        if ($expr instanceof Expr\Instanceof_) {
            return true;
        }

        return $expr instanceof Expr\FuncCall
            && $expr->name instanceof Node\Name
            && $this->isNarrowingFunction($expr->name->toString());
    }

    private function isNarrowingFunction(string $name): bool
    {
        return in_array(strtolower(ltrim($name, '\\')), [
            'is_array', 'is_string', 'is_int', 'is_integer', 'is_float', 'is_double',
            'is_bool', 'is_numeric', 'is_object', 'is_callable', 'is_iterable',
            'is_scalar', 'is_a', 'is_countable',
        ], true);
    }

    private function pipelineName(Expr\FuncCall $call): ?string
    {
        if (! $call->name instanceof Node\Name) {
            return null;
        }

        $name = strtolower($call->name->toString());

        return isset(self::ARRAY_PIPELINE_FUNCTIONS[$name]) ? $name : null;
    }

    /**
     * The direct argument values of $call that are themselves pipeline calls.
     *
     * @return list<Expr\FuncCall>
     */
    private function pipelineArgCalls(Expr\FuncCall $call): array
    {
        $nested = [];

        foreach ($call->getArgs() as $arg) {
            if ($arg->value instanceof Expr\FuncCall && $this->pipelineName($arg->value) !== null) {
                $nested[] = $arg->value;
            }
        }

        return $nested;
    }

}
