<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\ReceiverTypeResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag `$x = <cond> ? new T(...) : null;` whose result is then defended with `?->` / `??`, and suggest a TOTAL/coalescing factory (`T::coalesce(...)`) that always returns a usable instance — so the condition, the null, and every downstream null-guard collapse into one call.
 */
#[IntroducedIn('2.73.0')]
class PreferCoalescingFactoryProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Build a wrapper via a total/coalescing factory, not `cond ? new T(...) : null` + null-guards';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A value wrapper is built as `$x = <cond> ? new T(...) : null` and then accessed DEFENSIVELY — `$x?->get(...)`, `$x ?? …`. The condition guards the construction, and the null forces a guard at every use. A TOTAL factory — `T::coalesce($raw)` / `T::for($raw)` that returns an EMPTY T when the input is absent/invalid — absorbs the condition and is never null, so `$x->get(...)` needs no guard.')
            ->leaveWhen('the null is a MEANINGFUL absent that callers branch on (`if ($x === null)` with a distinct path), not just `?->`/`??`-defended; or T genuinely has no empty/total form (its absence is irreducible).')
            ->whenUnsure('if every use of `$x` is `?->`/`??`-guarded, give T a static total factory that takes the raw input, handles the invalid case internally, and returns a usable (possibly empty) instance — then drop the `?:` and all the guards.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`$x = <cond> ? new T(...) : null` makes a wrapper that is null half the time, so
every downstream access must defend with `?->` and `??`. The condition, the null,
and the guards are all one missing idea: a TOTAL factory on T that takes the raw
input, handles the invalid/absent case ITSELF, and always returns a usable T.

Bad — construct-or-null, then guard at every use:
    $bag  = is_array($entry) ? new Fluent($entry) : null;
    $name = $bag?->get('name') ?? (is_string($entry) ? $entry : null);
    $match = $bag?->get('match') ?? $name;

Good — a total factory absorbs the condition; the bag is never null:
    $bag  = Fluent::coalesce($entry);     // empty Fluent when $entry is not an array
    $name = $bag->get('name');
    $match = $bag->get('match', $name);

WHAT FIRES — `$x = <cond> ? new T(...) : null` (either branch order) assigned to a
variable that is later consumed via `?->` or as the left of `??` in the same
function — the construct-or-null whose null is immediately defended, not branched
on.

WHAT DOES NOT — a `cond ? new T(...) : null` whose null is a real "absent" the
caller branches on (`if ($x === null)`), a ternary with no `new`, or a result
that is never null-guarded.
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

        foreach ($finder->findInstanceOf($ast, Expr\Assign::class) as $assign) {
            if (! $assign->var instanceof Expr\Variable || ! is_string($assign->var->name)) {
                continue;
            }

            if (! $assign->expr instanceof Expr\Ternary || $assign->expr->if === null) {
                continue;
            }

            $new = $this->constructOrNull($assign->expr);

            if ($new === null) {
                continue;
            }

            $fn = ReceiverTypeResolver::enclosingFunction($assign, $ast);

            if ($fn === null || ! $this->nullGuarded($fn, $assign->var->name, $finder)) {
                continue;
            }

            $line = $assign->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                sprintf(
                    '`$%s = … ? new %s(…) : null` then defended with `?->`/`??` — give %s a total factory (`%s::coalesce($raw)`) that returns an empty instance for absent input, so $%s is never null and the guards vanish.',
                    $assign->var->name,
                    $this->newName($new),
                    $this->newName($new),
                    $this->newName($new),
                    $assign->var->name,
                ),
                $this->lineSnippet($content, $line),
                'coalescing-factory:' . $assign->var->name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /** The `new T(...)` of a `cond ? new T : null` / `cond ? null : new T` ternary, else null. */
    private function constructOrNull(Expr\Ternary $ternary): ?Expr\New_
    {
        if ($ternary->if instanceof Expr\New_ && $this->isNull($ternary->else)) {
            return $ternary->if;
        }

        if ($this->isNull($ternary->if) && $ternary->else instanceof Expr\New_) {
            return $ternary->else;
        }

        return null;
    }

    /** Whether $name is read with `?->` or as the left of `??` anywhere in $fn. */
    private function nullGuarded(Node\FunctionLike $fn, string $name, NodeFinder $finder): bool
    {
        foreach ($finder->find([$fn], static fn (Node $n): bool =>
            $n instanceof Expr\NullsafeMethodCall || $n instanceof Expr\NullsafePropertyFetch) as $nullsafe
        ) {
            if ($nullsafe->var instanceof Expr\Variable && $nullsafe->var->name === $name) {
                return true;
            }
        }

        foreach ($finder->findInstanceOf([$fn], Expr\BinaryOp\Coalesce::class) as $coalesce) {
            if ($coalesce->left instanceof Expr\Variable && $coalesce->left->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function isNull(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch && strtolower($expr->name->toString()) === 'null';
    }

    private function newName(Expr\New_ $new): string
    {
        return $new->class instanceof Node\Name ? $new->class->getLast() : 'T';
    }
}
