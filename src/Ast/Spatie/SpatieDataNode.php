<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Spatie;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\DataClassShape;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;

/**
 * The `spatie/laravel-data` knowledge, as a node: whether a class IS a `Data` subclass, whether a
 * `new`/`::from()` targets one, whether the class is RICH (has a cast/map/nest/factory), and the
 * `::collect()`-migration semantics (per-item hydration, the tolerant-catch and keyed-map
 * exemptions). The `Data` FQCN lives here once; a detector reads `$n->isDataClass()`. Reached by
 * type-hinting it in a `where` closure.
 */
final class SpatieDataNode extends NodeMatch
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    /**
     * Is this class declaration a Spatie `Data` subclass?
     */
    public function isDataClass(): bool
    {
        return $this->codebase?->extends($this->enclosingClassName(), self::DATA) ?? false;
    }

    /**
     * Is this `new X(...)` constructing a `Data` subclass?
     */
    public function isNewData(): bool
    {
        return $this->codebase?->extends($this->newClassName(), self::DATA) ?? false;
    }

    /**
     * Is this a static call whose receiver class is a `Data` subclass — e.g. `SomeData::from(...)`?
     */
    public function onDataClass(): bool
    {
        return $this->codebase?->extends($this->staticCallClass(), self::DATA) ?? false;
    }

    /**
     * Is the `Data` class this `new` constructs RICH — does it have a cast, name map, nested-Data
     * hydration, or a magic `fromX()` factory that a raw `new` would skip? (Delegated to the shared
     * {@see DataClassShape} shape analysis, which the repent scribe reuses too.)
     */
    public function isRichData(): bool
    {
        return $this->codebase !== null
            && DataClassShape::forCodebase($this->codebase)->isRich($this->newClassName(), $this->codebase);
    }

    /**
     * Is this node inside a loop OR an `array_map` callback — the two shapes the spatie-data skill
     * names as per-item hydration that `::collect()` replaces.
     */
    public function isPerItemHydration(): bool
    {
        return $this->isWithinLoop() || $this->isWithinArrayMap();
    }

    /**
     * Is this node inside a `try` whose `catch` SKIPS the failed item — a `continue` or `return`
     * in a catch clause? That marks a TOLERANT decoder (drop a malformed entry and keep going);
     * `::collect()` is all-or-nothing and throws on the first bad row, so it cannot express the
     * per-entry skip. The try is matched only when it is itself inside a loop, so a method-level
     * try/catch around the whole map doesn't grant the exemption.
     */
    public function isWithinTolerantCatch(): bool
    {
        $try = $this->walkUp(static fn (Node $node): bool => $node instanceof TryCatch);

        if (! $try instanceof TryCatch || ! self::within($try, static fn (Node $n): bool =>
            $n instanceof Foreach_ || $n instanceof For_ || $n instanceof While_ || $n instanceof Do_)) {
            return false;
        }

        foreach ($try->catches as $catch) {
            if ((new NodeFinder)->findFirst($catch->stmts, static fn (Node $n): bool =>
                $n instanceof Continue_ || $n instanceof Return_) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this node the right-hand side of an assignment into a KEYED map — `$out[$id] = X::from(...)`?
     * `::collect()` returns a LIST; it cannot key by a computed value, so a keyed-map build is not
     * the one-pass mapping the skill replaces. A plain list append (`$out[] = …`) is NOT exempt.
     */
    public function isKeyedMapAssignment(): bool
    {
        $assign = $this->walkUp(static fn (Node $node): bool => $node instanceof Assign);

        return $assign instanceof Assign
            && $assign->var instanceof ArrayDimFetch
            && $assign->var->dim !== null;
    }
}
