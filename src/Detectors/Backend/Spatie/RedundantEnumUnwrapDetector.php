<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantEnumUnwrapScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantEnumUnwrap;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags an enum destructured to `->value` inside a `Data::from([...])` slot whose property is typed as that
 * same enum (`'status' => $order->status->value`). Spatie's enum cast re-hydrates the scalar straight back
 * into the enum, so the unwrap is a needless round-trip — pass the enum itself. The mirror of
 * {@see RedundantNativeCastDetector} (which flags the scalar→enum construction).
 */
final class RedundantEnumUnwrapDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new RedundantEnumUnwrap();
    }

    public function scribe(): string
    {
        return RedundantEnumUnwrapScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isPropertyFetchNamed('value'))
            ->where(static fn (SpatieDataNode $node): bool => $node->isEnumUnwrapIntoItsOwnSlot())
            ->get();
    }
}
