<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Sins\Frontend\CompoundInlineComponent;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use JesseGall\CodeCommandments\Vue\Detector;

/**
 * A compound UI primitive assembled INLINE — a component (`<Dialog>`, `<Card>`,
 * `<Sheet>`, `<Tabs>`) whose family parts (`DialogContent`/`DialogTitle`/`DialogFooter`)
 * are filled with a substantial body right here in the parent template, instead of
 * living in its own component. The `<Dialog>…</Dialog>` welded into a settings page IS
 * a `PairingDialog`; lift it out.
 *
 * Structural, no name list: the fingerprint is a COMPONENT root with two-or-more
 * descendant components sharing its tag as a prefix (`Dialog` + `Dialog*`) — the shape
 * of every library compound — gated on a substantial inline body so a trivial confirm
 * dialog is left alone.
 *
 * Repentable — {@see ExtractComponentScribe} lifts it into a component named for what it
 * does (its title + family: `PairReaderDialog`).
 */
final class CompoundInlineComponentDetector implements Detector, Repentable
{
    /** elements — below this the compound is too small to be its own component. */
    private const int MIN_BODY = 12;

    public function sin(): Sin
    {
        return new CompoundInlineComponent();
    }

    public function scribe(): ExtractComponentScribe
    {
        return ExtractComponentScribe::forCompound();
    }

    public function find(Codebase $components): array
    {
        return $components
            ->whereElement()
            ->where(static fn (ElementMatch $element): bool => $element->isComponent())
            ->where(static fn (ElementMatch $element): bool => count($element->compoundParts()) >= 2)
            ->where(static fn (ElementMatch $element): bool => $element->subtreeSize() >= self::MIN_BODY)
            ->reject(static fn (ElementMatch $element): bool => $element->depth() === 1 && count($element->parent?->elements() ?? []) === 1)
            ->get();
    }
}
