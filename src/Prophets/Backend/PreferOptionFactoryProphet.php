<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag a conditional that hand-rolls `Option::some(...)` / `Option::none()` —
 * `$cond ? Option::some($v) : Option::none()` (or the `if/else` equivalent) —
 * which is exactly what the Option factory methods exist to express. Building
 * the present/absent decision with the factory keeps the construction in one
 * named place instead of an open-coded branch the reader must decode.
 *
 *     // hand-rolled
 *     return $this->reg->has($id)
 *         ? Option::some($this->reg->node($id)->descriptor)
 *         : Option::none();
 *
 *     // factory
 *     return Option::when($this->reg->has($id), fn () => $this->reg->node($id)->descriptor);
 *
 * The suggested factory follows the shape of the branch:
 *   - a null-check (`$x !== null ? some($x) : none()`)        → `Option::make($x)`
 *   - a key probe (`isset($a[$k]) ? some($a[$k]) : none()`)   → `Option::find($a, $k)`
 *   - any other condition                                     → `Option::when($cond, fn () => $v)`
 *     (`whenNot` when `some` is on the false branch).
 *
 * Advisory, never a sin — collapsing to a factory is a readability call.
 */
#[IntroducedIn('2.2.0')]
class PreferOptionFactoryProphet extends PhpCommandment
{
    private const DEFAULT_OPTION_CLASS = 'App\\Support\\Option';

    public function description(): string
    {
        return 'Build an Option with a factory (make/find/when), not a hand-rolled some()/none() branch';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    /**
     * Never flag the Option class's OWN definition — `when()`/`make()` are
     * implemented in terms of `some()`/`none()`.
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        $class = ltrim((string) ($this->config('option_class') ?: self::DEFAULT_OPTION_CLASS), '\\');

        return $class === '' ? [] : [$class];
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A ternary or `if/else` returns `Option::some($v)` on one side and `Option::none()` on the other — it hand-rolls the present/absent construction the Option factories (`make`/`find`/`when`/`whenNot`) already express in one named call.')
            ->leaveWhen('the two branches do real, differing work beyond `some`/`none` (each wraps a different value, or one branch has side effects), so there is no single factory that captures it; or the `some`/`none` are not adjacent branches of one decision.')
            ->whenUnsure('reach for the factory whose shape matches: `make($x)` for `$x !== null`, `find($a, $k)` for `isset($a[$k])`, otherwise `when($cond, fn () => $v)` (`whenNot` when `some` is the false branch).');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
The Option factories exist so the "is it there?" decision is one named call, not
an open-coded `some`/`none` branch every reader has to re-derive.

Bad — hand-rolled across a ternary (or if/else):
    return $this->reg->has($id)
        ? Option::some($this->reg->node($id)->descriptor)
        : Option::none();

Good — the factory whose shape matches the branch:
    // a plain condition
    return Option::when($this->reg->has($id), fn () => $this->reg->node($id)->descriptor);

    // a null check  ->  make() (lifts a nullable: null => none, else some)
    return Option::make($value);                  // was: $value !== null ? Option::some($value) : Option::none()

    // a key probe   ->  find()
    return Option::find($this->items, $key);      // was: isset($this->items[$key]) ? Option::some($this->items[$key]) : Option::none()

    // some on the FALSE branch -> whenNot()
    return Option::whenNot($isHidden, fn () => $value);

WHAT FIRES — a `?:` ternary, or an `if (…) { return Option::some($v); } else {
return Option::none(); }`, whose two outcomes are exactly `Option::some(...)` and
`Option::none()` (in either order).

WHAT DOES NOT — branches that wrap DIFFERENT values or do other work, a short
`?:`, or `some`/`none` that are not the two arms of one decision. Advisory —
collapsing to a factory is a readability call, not auto-fixed (the right factory
depends on intent).
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Expr\Ternary::class) as $ternary) {
            if ($ternary->if === null) {
                continue; // short `?:`
            }

            $match = $this->matchSomeNone($ternary->if, $ternary->else);

            if ($match !== null) {
                $warnings[] = $this->warn($ternary->getStartLine(), $ternary->cond, $match, $content);
            }
        }

        foreach ($finder->findInstanceOf($ast, Node\Stmt\If_::class) as $if) {
            if ($if->elseifs !== [] || $if->else === null) {
                continue;
            }

            $then = $this->singleReturnExpr($if->stmts);
            $else = $this->singleReturnExpr($if->else->stmts);

            if ($then === null || $else === null) {
                continue;
            }

            $match = $this->matchSomeNone($then, $else);

            if ($match !== null) {
                $warnings[] = $this->warn($if->getStartLine(), $if->cond, $match, $content);
            }
        }

        // `Option::make($cond ? $value : null)` — the some/none ternary collapsed
        // INTO make(), but the branch is still hand-rolled. That IS `when()`.
        foreach ($finder->findInstanceOf($ast, Expr\StaticCall::class) as $call) {
            if (! $this->isOptionStaticCall($call, 'make')) {
                continue;
            }

            $args = $call->getArgs();

            if (count($args) !== 1 || ! $args[0]->value instanceof Expr\Ternary) {
                continue;
            }

            $ternary = $args[0]->value;

            if ($ternary->if === null) {
                continue; // short `?:`
            }

            if ($this->isNullLiteral($ternary->else)) {
                $value = $ternary->if;
                $someOnFalse = false;
            } elseif ($this->isNullLiteral($ternary->if)) {
                $value = $ternary->else;
                $someOnFalse = true;
            } else {
                continue; // neither branch is null — not a present/absent decision
            }

            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf('Wraps a `? … : null` ternary in `%s::make()` — that re-hand-rolls the present/absent decision inside the factory. Use the conditional factory directly: `%s`.', $this->optionShort(), $this->suggestion($value, $ternary->cond, $someOnFalse, $content)),
                $this->lineAt($content, $call->getStartLine()),
                'option-make-ternary',
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  array{arg: Expr, someOnFalse: bool}  $match
     */
    private function warn(int $line, Expr $cond, array $match, string $content): \JesseGall\CodeCommandments\Results\Warning
    {
        $suggestion = $this->suggestion($match['arg'], $cond, $match['someOnFalse'], $content);

        return $this->warningAt(
            $line,
            sprintf('Hand-rolls `%s::some()`/`%s::none()` across a condition — build it with the factory instead: `%s`.', $this->optionShort(), $this->optionShort(), $suggestion),
            $this->lineAt($content, $line),
            'option-some-none-branch',
        );
    }

    /**
     * If {$a, $b} are exactly an `Option::some(arg)` / `Option::none()` pair,
     * return the wrapped arg and whether `some` is the FALSE-side outcome.
     *
     * @return array{arg: Expr, someOnFalse: bool}|null
     */
    private function matchSomeNone(Expr $a, Expr $b): ?array
    {
        $someA = $this->someArg($a);

        if ($someA !== null && $this->isNone($b)) {
            return ['arg' => $someA, 'someOnFalse' => false];
        }

        $someB = $this->someArg($b);

        if ($someB !== null && $this->isNone($a)) {
            return ['arg' => $someB, 'someOnFalse' => true];
        }

        return null;
    }

    private function someArg(Expr $expr): ?Expr
    {
        if ($this->isOptionStaticCall($expr, 'some') && $expr instanceof Expr\StaticCall) {
            $args = $expr->getArgs();

            return count($args) === 1 ? $args[0]->value : null;
        }

        return null;
    }

    private function isNone(Expr $expr): bool
    {
        return $this->isOptionStaticCall($expr, 'none')
            && $expr instanceof Expr\StaticCall
            && $expr->getArgs() === [];
    }

    private function isOptionStaticCall(Expr $expr, string $method): bool
    {
        return $expr instanceof Expr\StaticCall
            && $expr->class instanceof Node\Name
            && $expr->class->getLast() === $this->optionShort()
            && $expr->name instanceof Node\Identifier
            && strtolower($expr->name->toString()) === $method;
    }

    private function suggestion(Expr $arg, Expr $cond, bool $someOnFalse, string $content): string
    {
        $option = $this->optionShort();
        $argSrc = $this->src($arg, $content);

        // Null-check → make($x).
        if ($this->isNonNullCheckOf($cond, $argSrc, $someOnFalse, $content)) {
            return sprintf('%s::make(%s)', $option, $argSrc);
        }

        // Key probe → find($arr, $key).
        if (! $someOnFalse) {
            $find = $this->findFromKeyProbe($cond, $arg, $content);

            if ($find !== null) {
                return sprintf('%s::find(%s, %s)', $option, $find['array'], $find['key']);
            }
        }

        $factory = $someOnFalse ? 'whenNot' : 'when';

        return sprintf('%s::%s(%s, fn () => %s)', $option, $factory, $this->src($cond, $content), $argSrc);
    }

    /**
     * Whether the "some-when" condition is exactly `<arg> !== null` — i.e. a
     * `!==`/`!=` against null (some on TRUE) or `===`/`==` against null (some on
     * FALSE, so the negation is non-null), whose other operand is the wrapped arg.
     */
    private function isNonNullCheckOf(Expr $cond, string $argSrc, bool $someOnFalse, string $content): bool
    {
        $isEquality = $cond instanceof Expr\BinaryOp\Identical || $cond instanceof Expr\BinaryOp\Equal;
        $isInequality = $cond instanceof Expr\BinaryOp\NotIdentical || $cond instanceof Expr\BinaryOp\NotEqual;

        if (! $isEquality && ! $isInequality) {
            return false;
        }

        /** @var Expr\BinaryOp $cond */
        $other = $this->nonNullOperand($cond);

        if ($other === null) {
            return false;
        }

        // some-when must be "<arg> !== null": inequality with some-on-true, OR
        // equality with some-on-false (the else branch fires on non-null).
        $someWhenIsNonNull = $isInequality ? ! $someOnFalse : $someOnFalse;

        return $someWhenIsNonNull && $this->src($other, $content) === $argSrc;
    }

    private function nonNullOperand(Expr\BinaryOp $op): ?Expr
    {
        if ($this->isNullLiteral($op->right)) {
            return $op->left;
        }

        if ($this->isNullLiteral($op->left)) {
            return $op->right;
        }

        return null;
    }

    /**
     * `isset($arr[$key])` / `array_key_exists($key, $arr)` whose probed element
     * is the wrapped arg → the array + key sources for `find()`.
     *
     * @return array{array: string, key: string}|null
     */
    private function findFromKeyProbe(Expr $cond, Expr $arg, string $content): ?array
    {
        if ($cond instanceof Expr\Isset_ && count($cond->vars) === 1) {
            $var = $cond->vars[0];

            if ($var instanceof Expr\ArrayDimFetch
                && $var->dim !== null
                && $this->src($var, $content) === $this->src($arg, $content)
            ) {
                return ['array' => $this->src($var->var, $content), 'key' => $this->src($var->dim, $content)];
            }
        }

        if ($cond instanceof Expr\FuncCall
            && $cond->name instanceof Node\Name
            && strtolower($cond->name->toString()) === 'array_key_exists'
            && count($cond->getArgs()) === 2
            && $arg instanceof Expr\ArrayDimFetch
            && $arg->dim !== null
        ) {
            return ['array' => $this->src($arg->var, $content), 'key' => $this->src($arg->dim, $content)];
        }

        return null;
    }

    private function isNullLiteral(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch && strtolower($expr->name->toString()) === 'null';
    }

    /**
     * @param  array<Node\Stmt>  $stmts
     */
    private function singleReturnExpr(array $stmts): ?Expr
    {
        return count($stmts) === 1 && $stmts[0] instanceof Node\Stmt\Return_
            ? $stmts[0]->expr
            : null;
    }

    private function optionShort(): string
    {
        $class = (string) ($this->config('option_class') ?: self::DEFAULT_OPTION_CLASS);
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private function src(Node $node, string $content): string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        return $start >= 0 && $end >= $start ? substr($content, $start, $end - $start + 1) : '';
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
