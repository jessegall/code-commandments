<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Sins\Frontend\CompoundInlineComponent;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ElementMatch;
use JesseGall\CodeCommandments\Frontend\Detector;

/**
 * Detects compound UI primitives assembled inline (component root with ≥2 descendants
 * sharing a prefix tag). Repentable via ExtractComponentScribe.
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
