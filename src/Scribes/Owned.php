<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

/**
 * A step that runs someone else's rule — the {@see Scribe} or detector whose failure is really its
 * own. A run names WHOSE rule broke, and the package cannot answer for a rule it did not ship.
 */
interface Owned
{
    /**
     * The rule this step runs, for {@see \JesseGall\CodeCommandments\Custom::owns} to place.
     */
    public function owner(): object;
}
