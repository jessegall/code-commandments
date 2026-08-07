<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\SwallowCatch;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\Tags\ControlSignal;

/**
 * A `catch` that swallows the failure into absence — an empty body, or whose only
 * effect is `return null/false/[]`. The error vanishes silently and the caller
 * gets a fake "nothing happened". Either recover meaningfully, or let it
 * propagate; absorb only at one boundary, and LOG when you do. A caught type tagged
 * `ControlSignal` is exempt: it carries no failure, so catching it IS the semantics.
 * Points at exceptions.
 */
final class SwallowCatchDetector implements Detector, Exemptable
{
    public function sin(): Sin
    {
        return new SwallowCatch();
    }

    /**
     * The subject is the CAUGHT TYPE, not the enclosing class or method, so the reject is enforced
     * here rather than by the central `ExemptBy` scopes — declared so `commandments exemptions`
     * still lists what quiets this rule.
     */
    public function exemptions(): array
    {
        return [ControlSignal::class => []];
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isSwallowedCatch())
            ->reject(static fn (AstNode $node) => self::catchesOnlyControlSignals($codebase, $node))
            ->get();
    }

    /**
     * Does this catch name ONLY control signals? `catch (BreakSignal)` is loop-exit semantics, but
     * `catch (BreakSignal | RuntimeException)` still swallows a real failure on its second arm — so
     * EVERY alternative must be tagged, and a broad `catch (Throwable)` can never qualify.
     */
    private static function catchesOnlyControlSignals(Codebase $codebase, AstNode $node): bool
    {
        $types = $node->caughtTypes();

        return $types !== [] && array_all(
            $types,
            static fn (string $type) => $codebase->exemptions()->has(ControlSignal::class, $codebase, $type),
        );
    }
}
