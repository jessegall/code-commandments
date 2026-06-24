<?php

namespace App\EditorMetadata;

use Illuminate\Support\Fluent;

/**
 * One branch on a set-metadata action — its loose name-or-attribute shorthand
 * normalised ONCE, at the boundary, into a total typed bag.
 */
final class BranchSpec extends Fluent
{
    /**
     * Total factory: a bare string shorthand becomes its `label`; an attribute
     * array is taken as-is; anything else is an empty spec. Never null.
     */
    public static function coalesce(mixed $spec): self
    {
        if (is_string($spec)) {
            return new self(['label' => $spec]);
        }

        return new self(is_array($spec) ? $spec : []);
    }

    /** The branch label, or an empty string when the shorthand carried none. */
    public function label(): string
    {
        return $this->string('label');
    }

    /** Whether traversing this branch should also emit a match handle. */
    public function withMatch(): bool
    {
        return $this->boolean('match');
    }
}
