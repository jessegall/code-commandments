<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Evals;

/**
 * Answers the one question a trigger eval asks: shown nothing but the skill METADATA — the names and
 * descriptions, which is all a loader ever sees — which skills would be consulted for this prompt?
 *
 * An interface because the answer comes from a model, and a scoring run that can only be exercised by
 * spending money on one is a scoring run nobody tests.
 */
interface Oracle
{
    /**
     * @param  array<string, string>  $skills  id => description, the whole visible surface
     * @return list<string>  the ids it would consult
     */
    public function consulted(string $query, array $skills): array;
}
