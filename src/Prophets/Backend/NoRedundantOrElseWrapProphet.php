<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag `->orElse(fn () => Option::some($x))` — manually wrapping the alternative
 * in an Option. `orElse()` already lifts a bare value into an Option (a plain
 * value becomes `some`, null becomes `none`), so the `Option::some(...)` /
 * `Option::make(...)` wrap is dead boilerplate. Return the value directly:
 * `->orElse(fn () => $x)`.
 */
#[IntroducedIn('1.136.0')]
class NoRedundantOrElseWrapProphet extends PhpCommandment implements SinRepenter
{
    /** @var list<string> Option chain methods that lift a bare alternative themselves. */
    private const DEFAULT_METHODS = ['orElse'];

    /** @var list<string> Static wrap constructors that become redundant inside those methods. */
    private const WRAP_CONSTRUCTORS = ['some', 'make'];

    public function description(): string
    {
        return 'Do not manually wrap an orElse alternative in Option::some()/make()';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'An `->orElse(...)` callback returns `Option::some($x)` or '
                . '`Option::make($x)` — hand-wrapping the alternative. `orElse()` '
                . 'already lifts a bare value into an Option, so the wrap is dead '
                . 'boilerplate; return `$x` directly.'
            )
            ->leaveWhen(
                'The callback returns `Option::none()`, or a conditionally-built '
                . 'Option (`Option::when(...)`, `Option::someWhen(...)`, another '
                . 'chain) — those are not a plain value wrap and removing them '
                . 'would change behaviour. Only a single-argument some()/make() '
                . 'wrap is flagged.'
            )
            ->whenUnsure(
                'If you deliberately need an Option that is PRESENT but holds null '
                . '(`some(null)`), keep the explicit wrap — `orElse()` lifts a bare '
                . 'value with `make()`, which collapses null to `none`. Otherwise '
                . 'drop the wrap.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`Option::orElse()` is the lazy "or" — it returns $this when present, otherwise
the alternative the callback produces. The callback may return EITHER an Option
OR a bare value: a bare value is lifted automatically (a plain value becomes
`some`, `null` becomes `none`). So wrapping the alternative yourself in
`Option::some(...)` / `Option::make(...)` is pure boilerplate that `orElse()`
would have done for you.

Bad — hand-wrapping the alternative:
    $workflow
        ->transform(fn (Workflow $w): int => $this->report($w))
        ->orElse(fn () => Option::some($this->respondError("not found")))
        ->getOrThrow();

Good — return the value directly:
    $workflow
        ->transform(fn (Workflow $w): int => $this->report($w))
        ->orElse(fn () => $this->respondError("not found"))
        ->getOrThrow();

WHAT FIRES — an `orElse()` callback (arrow fn or single-`return` closure) whose
result is a single-argument `Option::some($x)` or `Option::make($x)`. Both are
auto-fixed by unwrapping to `$x`.

WHAT DOES NOT — `Option::none()` (no value to unwrap), a conditionally-built
Option (`Option::when(...)`, `Option::someWhen(...)`), or any other chain: those
are real Options, not a redundant wrap, so they are left alone.

EDGE CASE — `Option::some(null)` is an Option that is PRESENT but holds null;
`orElse()` lifts a bare value with `make()`, which turns null into `none`. If you
truly need present-but-null, keep the explicit `some()`. This is vanishingly
rare; everywhere else, drop the wrap.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $methods = $this->methods();
        $wrapShortName = $this->optionShortName();
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), $methods, true)
                || $call->isFirstClassCallable()
            ) {
                continue;
            }

            $args = $call->getArgs();

            if (count($args) !== 1) {
                continue;
            }

            $wrap = $this->redundantWrap($args[0]->value, $wrapShortName);

            if ($wrap === null) {
                continue;
            }

            $method = $call->name->toString();

            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf(
                    '`->%s(...)` hand-wraps its alternative in `%s::%s(...)`, but `%s()` already lifts a bare value into an Option (a plain value becomes `some`, null becomes `none`). Drop the wrap and return the value directly: `->%s(fn () => $value)`.',
                    $method,
                    $wrapShortName,
                    $wrap->name->toString(),
                    $method,
                    $method,
                ),
                $this->lineAt($content, $call->getStartLine()),
                'orelse-wrap:' . $method,
                true,
            );
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $methods = $this->methods();
        $wrapShortName = $this->optionShortName();

        /** @var list<array{start: int, end: int, text: string}> $edits */
        $edits = [];
        $penance = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), $methods, true)
                || $call->isFirstClassCallable()
            ) {
                continue;
            }

            $args = $call->getArgs();

            if (count($args) !== 1) {
                continue;
            }

            $alternative = $args[0]->value;
            $wrap = $this->redundantWrap($alternative, $wrapShortName);

            if ($wrap === null) {
                continue;
            }

            $inner = $wrap->getArgs()[0]->value;

            $innerStart = (int) $inner->getStartFilePos();
            $innerEnd = (int) $inner->getEndFilePos();

            $edits[] = [
                'start' => (int) $wrap->getStartFilePos(),
                'end' => (int) $wrap->getEndFilePos(),
                'text' => substr($content, $innerStart, $innerEnd - $innerStart + 1),
            ];

            // #101: the closure declared `: Option` because it used to RETURN an
            // Option. Unwrapped, it returns the bare value, so that hint is now a
            // type lie (`fn (): Option => $intReturningCall()`). Drop it.
            $returnTypeEdit = $this->returnTypeRemoval($alternative, $content);

            if ($returnTypeEdit !== null) {
                $edits[] = $returnTypeEdit;
            }

            $penance[] = sprintf('Unwrapped %s::%s(...) inside ->%s(...) — orElse lifts the bare value itself', $wrapShortName, $wrap->name->toString(), $call->name->toString());
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * The redundant wrap inside an orElse callback, or null. Matches an arrow
     * function (`fn () => Option::some($x)`) or a single-`return` closure whose
     * result is a single-argument `Option::some(...)` / `Option::make(...)`.
     */
    private function redundantWrap(Expr $alternative, string $wrapShortName): ?Expr\StaticCall
    {
        $returned = $this->callbackResult($alternative);

        if ($returned === null) {
            return null;
        }

        if (! $returned instanceof Expr\StaticCall
            || ! $returned->class instanceof Node\Name
            || $returned->class->getLast() !== $wrapShortName
            || ! $returned->name instanceof Node\Identifier
            || ! in_array($returned->name->toString(), self::WRAP_CONSTRUCTORS, true)
        ) {
            return null;
        }

        $wrapArgs = $returned->getArgs();

        // Exactly one positional, non-spread argument is unwrappable.
        if (count($wrapArgs) !== 1 || $wrapArgs[0]->unpack) {
            return null;
        }

        return $returned;
    }

    /**
     * The edit that strips an arrow/closure's explicit return-type hint (the
     * `: Option` between the parameter list and `=>`/`{`), or null when the
     * callback declares no return type. Removes from the `:` through the type.
     *
     * @return array{start: int, end: int, text: string}|null
     */
    private function returnTypeRemoval(Expr $alternative, string $content): ?array
    {
        if (! $alternative instanceof Expr\ArrowFunction && ! $alternative instanceof Expr\Closure) {
            return null;
        }

        $returnType = $alternative->returnType;

        if ($returnType === null) {
            return null;
        }

        $typeStart = (int) $returnType->getStartFilePos();
        $colon = strrpos(substr($content, 0, $typeStart), ':');

        if ($colon === false) {
            return null;
        }

        return ['start' => $colon, 'end' => (int) $returnType->getEndFilePos(), 'text' => ''];
    }

    /**
     * The single result expression of a callback argument — the body of an arrow
     * function, or the lone returned value of a one-statement closure. Null for
     * anything else (a non-closure, or a multi-statement body we cannot safely
     * reduce to one expression).
     */
    private function callbackResult(Expr $alternative): ?Expr
    {
        if ($alternative instanceof Expr\ArrowFunction) {
            return $alternative->expr;
        }

        if ($alternative instanceof Expr\Closure && count($alternative->stmts) === 1) {
            $stmt = $alternative->stmts[0];

            if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof Expr) {
                return $stmt->expr;
            }
        }

        return null;
    }

    private function optionShortName(): string
    {
        $class = ltrim((string) ($this->config('option_class') ?: 'App\\Support\\Option'), '\\');
        $short = strrchr($class, '\\');

        return $short === false ? ($class === '' ? 'Option' : $class) : substr($short, 1);
    }

    /**
     * @return list<string>
     */
    private function methods(): array
    {
        $configured = $this->config('methods', self::DEFAULT_METHODS);

        return is_array($configured) && $configured !== []
            ? array_values(array_filter($configured, 'is_string'))
            : self::DEFAULT_METHODS;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
