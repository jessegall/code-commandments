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
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Flag a not-found exception caught and replaced with a sentinel: a
 * `try { … } catch (SomethingNotFound) { $x = null; }` (or `= false` / `= []`,
 * or `return` of one of those) whose catch body does NOTHING but swallow the
 * miss into an absence value.
 *
 * A "not found" thrown by a lookup that the caller has already established MUST
 * resolve (an authenticated user id, a foreign key, a required template) is an
 * invariant violation. Swallowing it into `null`/`false`/`[]` turns a loud,
 * locatable bug into a silent wrong-default that every caller then compensates
 * for. Let it throw — or, if the absence is genuinely expected, model it
 * explicitly (an `Option`, a real default) at the source instead of catching the
 * exception just to discard it.
 *
 * The sibling rungs of the invariant ladder: an enum `default => null` →
 * {@see ThrowOnUnhandledCaseProphet}; a registry miss →
 * {@see RegistryReturnContractProphet}; a method every caller de-nulls →
 * {@see PreferTotalOverNullableProphet}.
 *
 * Advisory, never a sin; not auto-fixable (rethrowing changes runtime behaviour
 * — a deliberate call).
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static notFoundExceptions(array $value)
 * @method-generated-end
 */
#[IntroducedIn('2.0.0')]
class NoSwallowedNotFoundProphet extends PhpCommandment
{
    /**
     * Exact exception short names treated as "not found" (in addition to the
     * always-on `*NotFound*` substring rule). Configurable via
     * `not_found_exceptions`.
     */
    private const DEFAULT_NOT_FOUND = ['OutOfBoundsException', 'OutOfRangeException', 'RuntimeException'];

    public function description(): string
    {
        return 'Do not catch a not-found exception just to swallow it into null/false/[] — let it throw';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A `try`/`catch` whose caught type is a "not found" exception (`*NotFound*`, `OutOfBounds`/`OutOfRange`/`RuntimeException`) has a body that ONLY assigns or returns a sentinel (`null` / `false` / `[]`). The looked-up thing is one the surrounding code already requires (an authenticated user, a foreign key, a per-severity template) — so the miss is a bug, and swallowing it hides it.')
            ->leaveWhen('the absence is genuinely expected here (a probe / best-effort lookup), the catch does real recovery (retry, fallback fetch, logging + rethrow), or the caught exception is not actually a "must exist" miss. Then either keep the handling, or model the absence explicitly as an `Option`/real default at the source rather than catching to discard.')
            ->whenUnsure('if the id/key was already established to exist, delete the try/catch and let it throw; if it may legitimately be absent, return an `Option`/a real default from the lookup itself instead of catching its exception.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A "not found" exception is the lookup telling you the thing you asked for is not
there. When the caller has ALREADY established it must be there — the id is the
authenticated user, a foreign key, a required per-severity template — that
exception is a real bug surfacing. Catching it only to set `$x = null` (or
`false`/`[]`) buries the bug: the node produces a silent wrong default, and every
downstream caller grows a compensation (`?-> … ?? 'Guest'`).

Bad — swallow the miss into a sentinel:
    try {
        $user = $this->users->getById($authenticatedUserId);
    } catch (UserNotFoundException) {
        $user = null;
    }
    return $user?->displayName() ?? 'Guest';   // a corrupt session silently becomes "Guest"

Good — the id is an invariant, so let it throw:
    return $this->users->getById($authenticatedUserId)->displayName();

Also good — if the absence is REAL, model it at the source (don't catch-to-discard):
    return $this->users->find($email)            // Option<User>
        ->map(fn (User $u) => $u->displayName())
        ->getOr('not found');

WHAT FIRES — a `try`/`catch` whose caught type is a not-found exception
(`*NotFound*`, or the configured `OutOfBoundsException`/`OutOfRangeException`/
`RuntimeException`) AND whose catch body is NOTHING but sentinel assignments or
returns (`$x = null|false|[]`, `return null|false|[]`).

WHAT DOES NOT — a catch that retries, fetches a fallback, logs and rethrows, maps
to a domain exception, or otherwise does real work; a caught type outside the
not-found set; a genuinely-expected absence modelled with an `Option`/default.

NOT auto-fixable — deleting a catch changes runtime behaviour (callers that
handled the miss would suddenly throw). Resolve by hand: if the value must exist,
let it throw; if it may be absent, return an Option/real default at the source.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        /** @var array<Stmt\TryCatch> $tries */
        $tries = (new NodeFinder)->findInstanceOf($ast, Stmt\TryCatch::class);

        foreach ($tries as $try) {
            foreach ($try->catches as $catch) {
                if (! $this->catchesNotFound($catch) || ! $this->isSentinelOnly($catch->stmts)) {
                    continue;
                }

                $line = $catch->getStartLine();
                $warnings[] = $this->warningAt(
                    $line,
                    sprintf(
                        'A %s is caught and swallowed into a sentinel (%s) — if the looked-up value is one the caller requires, this hides an invariant violation behind a silent default. Let it throw; or if the absence is genuine, return an Option/real default from the lookup itself instead of catching to discard.',
                        $this->caughtName($catch),
                        $this->sentinelDescription($catch->stmts),
                    ),
                    $this->lineSnippet($content, $line),
                    'swallowed-notfound:' . $this->symbolFor($catch),
                );

                // One finding per catch clause is enough; don't double-report a
                // multi-type catch.
                continue;
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function catchesNotFound(Stmt\Catch_ $catch): bool
    {
        foreach ($catch->types as $type) {
            $short = self::shortName($type->toString());

            if (in_array($short, $this->exactNotFound(), true)) {
                return true;
            }

            if (stripos($short, 'NotFound') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the catch body does NOTHING but assign/return a sentinel value —
     * the pure-swallow shape. An empty body, real recovery, logging, or a rethrow
     * all disqualify it.
     *
     * @param  array<Stmt>  $stmts
     */
    private function isSentinelOnly(array $stmts): bool
    {
        if ($stmts === []) {
            return false;
        }

        foreach ($stmts as $stmt) {
            if (! $this->isSentinelStmt($stmt)) {
                return false;
            }
        }

        return true;
    }

    private function isSentinelStmt(Stmt $stmt): bool
    {
        if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Assign) {
            return $this->isSentinelValue($stmt->expr->expr);
        }

        if ($stmt instanceof Stmt\Return_) {
            return $stmt->expr !== null && $this->isSentinelValue($stmt->expr);
        }

        return false;
    }

    private function isSentinelValue(Expr $expr): bool
    {
        if ($expr instanceof Expr\ConstFetch) {
            $name = strtolower($expr->name->toString());

            return $name === 'null' || $name === 'false';
        }

        return $expr instanceof Expr\Array_ && $expr->items === [];
    }

    /**
     * @param  array<Stmt>  $stmts
     */
    private function sentinelDescription(array $stmts): string
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Expression && $stmt->expr instanceof Expr\Assign) {
                return $this->valueLabel($stmt->expr->expr);
            }

            if ($stmt instanceof Stmt\Return_ && $stmt->expr !== null) {
                return 'return ' . $this->valueLabel($stmt->expr);
            }
        }

        return 'a sentinel';
    }

    private function valueLabel(Expr $expr): string
    {
        if ($expr instanceof Expr\ConstFetch) {
            return strtolower($expr->name->toString());
        }

        return '[]';
    }

    private function symbolFor(Stmt\Catch_ $catch): string
    {
        foreach ($catch->stmts as $stmt) {
            if ($stmt instanceof Stmt\Expression
                && $stmt->expr instanceof Expr\Assign
                && $stmt->expr->var instanceof Expr\Variable
                && is_string($stmt->expr->var->name)
            ) {
                return $stmt->expr->var->name;
            }
        }

        return self::shortName($catch->types[0]->toString());
    }

    private function caughtName(Stmt\Catch_ $catch): string
    {
        return self::shortName($catch->types[0]->toString());
    }

    /**
     * @return list<string>
     */
    private function exactNotFound(): array
    {
        $configured = $this->config('not_found_exceptions', self::DEFAULT_NOT_FOUND);

        return is_array($configured) && $configured !== []
            ? array_values(array_map(static fn ($e): string => self::shortName((string) $e), $configured))
            : self::DEFAULT_NOT_FOUND;
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

}
