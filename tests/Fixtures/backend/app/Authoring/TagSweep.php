<?php

namespace Shop\Authoring;

use JesseGall\CodeCommandments\Sins\Backend\CoalescedLoopSubject;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Weighs one field of an editor's submission against the shelf budget. Says it in the SHORT
 * form — `?: []` asks the same buried question as `?? []`, and buries it in the same place.
 */
final class TagSweep
{
    private const int SEPARATOR_WEIGHT = 1;

    /**
     * @param  array<string, mixed>  $submitted
     */
    #[Sinful(CoalescedLoopSubject::class)]
    public function weigh(array $submitted, string $field): int
    {
        $weight = 0;

        foreach ($submitted[$field] ?: [] as $entry) {
            $weight += strlen((string) $entry) + self::SEPARATOR_WEIGHT;
        }

        return $weight;
    }
}
