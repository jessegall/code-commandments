<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

/**
 * A scribe that REPENTS one detector's sin — the fix half of a {@see \JesseGall\CodeCommandments\Detectors\Repentable}
 * detector. It does NOT re-scan the codebase: the detector already queried and found
 * the sins, so the runner hands those findings straight in. The scribe starts from
 * them — each finding knows its exact {@see Span} — and may do LOCAL follow-up off a
 * finding (walk its siblings, read its expressions) to gather what the fix needs,
 * but never re-runs the whole query.
 *
 * It expresses the fix through the fluent {@see Draft} builder ({@see draft}) and
 * returns the `path => content` map; applying it is a separate collaborator's job.
 * Engine-agnostic by design — backend ({@see \JesseGall\CodeCommandments\Ast\NodeMatch})
 * and frontend ({@see \JesseGall\CodeCommandments\Vue\ElementMatch}) findings both
 * carry a `Span`, so a repent-scribe reads the same on either side of the system.
 */
abstract class RepentScribe
{
    /**
     * Rewrite the sins the detector found.
     *
     * @param  list<object>  $findings  the detector's matches (each exposes `->span()`)
     * @return array<string, string>  path => new content (changed/created files only)
     */
    abstract public function rewrite(array $findings): array;

    /**
     * The scribe's short name (its class basename) — used to select it.
     */
    public function name(): string
    {
        $parts = explode('\\', static::class);

        return end($parts);
    }

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
