<?php

namespace App\Workflow;

use Illuminate\Support\Fluent;

/**
 * One workflow output spec — a bare name string OR an attribute array — coalesced
 * into a single total bag, so callers read it the same way either form.
 */
final class OutputSpec extends Fluent
{
    /**
     * Total factory: a string shorthand becomes its `name`; anything that is not
     * an array becomes an empty spec. Never null.
     */
    public static function coalesce(mixed $spec): self
    {
        if (is_string($spec)) {
            return new self(['name' => $spec]);
        }

        return new self(is_array($spec) ? $spec : []);
    }

    /** The output name, or an empty string when the spec carries none. */
    public function name(): string
    {
        return $this->string('name');
    }

    public function matchOr(string $default): string
    {
        return $this->string('match', $default);
    }
}
