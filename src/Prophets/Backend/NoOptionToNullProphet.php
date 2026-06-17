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
 * Flag `->getOr(null)` — unwrapping an Option straight back into a nullable
 * value. That throws away the whole point of Option (no nulls to check) and
 * forces `?->` / `=== null` checks downstream. Act on the value with
 * `->map()`/`->each()`, require it with `->getOrThrow()`, or pass a REAL
 * default to `->getOr($default)`.
 */
#[IntroducedIn('1.74.0')]
class NoOptionToNullProphet extends PhpCommandment
{
    /** @var list<string> Option accessor methods whose null default is the smell. */
    private const DEFAULT_METHODS = ['getOr'];

    public function description(): string
    {
        return 'Do not unwrap an Option back to null with getOr(null)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'An Option is unwrapped with `getOr(null)`, turning it straight '
                . 'back into a nullable value the surrounding code then null-checks '
                . '(`?->`, `=== null`) — discarding the Option entirely.'
            )
            ->leaveWhen(
                'The value is genuinely handed to an external API whose contract '
                . 'is `?T` at a boundary and there is no Option-aware path. Rare — '
                . 'prefer map()/each()/getOrThrow().'
            )
            ->whenUnsure(
                'If you find yourself null-checking the result, use map()/each()/'
                . 'getOrThrow() instead. getOr() should carry a REAL default, never null.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`Option` exists so a value's presence is in the type, not a null you must
remember to check. `->getOr(null)` immediately undoes that — it converts
`Option<T>` back to `T|null`, and the code right after it goes back to
`?->`/`=== null` checks. You have paid for the Option and thrown it away.

Bad — unwrap to null, then null-check (the Option was pointless):
    $input = $this->inputByName($port)->getOr(null);
    if ($input?->socketType() === SocketType::Bag) { ... }

Good — stay inside the Option:
    // act only when present:
    $this->inputByName($port)->each(fn (Input $input) => /* … */);

    // map to another Option / value:
    $type = $this->inputByName($port)->map(fn (Input $i) => $i->socketType());

    // require it (throws if absent — when absence is a bug):
    $input = $this->inputByName($port)->getOrThrow();

    // or a REAL default (never null):
    $input = $this->inputByName($port)->getOr(Input::empty());

WHAT FIRES — a call to `getOr(null)` (the argument is the `null` literal).

WHAT DOES NOT — `getOr($realDefault)` with a genuine fallback, `getOrThrow()`,
`map()`, `each()`. Configure the method name(s) if your Option accessor differs:

    Backend\NoOptionToNullProphet::class => [
        'methods' => ['getOr'],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $methods = $this->methods();
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), $methods, true)
                || $call->isFirstClassCallable()
            ) {
                continue;
            }

            $args = $call->getArgs();

            if (count($args) !== 1 || ! $this->isNullLiteral($args[0]->value)) {
                continue;
            }

            $method = $call->name->toString();
            $warnings[] = $this->warningAt(
                $call->getStartLine(),
                sprintf(
                    '`->%s(null)` unwraps the Option back into a nullable value and forces null checks downstream — that is exactly what Option exists to avoid. Act on the value with `->map(...)`/`->each(...)`, require it with `->getOrThrow()`, or pass a REAL default to `->%s($default)`. Never `%s(null)`.',
                    $method,
                    $method,
                    $method,
                ),
                $this->lineAt($content, $call->getStartLine()),
                'option-to-null:' . $method,
            );
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    private function isNullLiteral(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && $expr->name instanceof Node\Name
            && strtolower($expr->name->toString()) === 'null';
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
