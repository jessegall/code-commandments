<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

/**
 * The fix half of a repentable detector; takes findings and expresses fixes through the
 * fluent Draft builder, engine-agnostic over findings with Spans.
 */
abstract class RepentScribe
{
    use NamedByClass;

    /**
     * Rewrite the sins the detector found.
     *
     * @param  list<object>  $findings  the detector's matches (each exposes `->span()`)
     * @return array<string, string>  path => new content (changed/created files only)
     */
    abstract public function rewrite(array $findings): array;

    /**
     * Open the fluent rewrite builder over the detector's findings — the scribe's
     * mirror of `$codebase->whereX()` opening a query.
     *
     * @param  list<mixed>  $findings
     */
    protected function draft(array $findings): Draft
    {
        return Draft::from($findings);
    }

    /**
     * Keep only the OUTERMOST findings — drop any whose span is nested inside another's. A
     * scribe that produces one artifact per finding (e.g. an extracted component) must not
     * act on both an outer block and a block it contains, or the outer ends up referencing
     * the inner. Engine-agnostic: every finding exposes a {@see Span} (backend `NodeMatch`,
     * frontend `ElementMatch` alike).
     *
     * @param  list<object>  $findings  each exposes `->span(): Span`
     * @return list<object>
     */
    protected function outermost(array $findings): array
    {
        return array_values(array_filter($findings, static function (object $candidate) use ($findings): bool {
            foreach ($findings as $other) {
                if ($candidate !== $other && $other->span()->contains($candidate->span())) {
                    return false;
                }
            }

            return true;
        }));
    }
}
