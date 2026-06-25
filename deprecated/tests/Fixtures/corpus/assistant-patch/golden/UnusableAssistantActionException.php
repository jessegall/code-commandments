<?php

namespace App\AssistantPatch;

use RuntimeException;

/**
 * Thrown when a raw action cannot be decoded into a usable, fully-identified action.
 */
final class UnusableAssistantActionException extends RuntimeException
{
    public static function unusablePayload(AssistantActionType $type): self
    {
        return new self("The {$type->value} payload is missing the fields needed to apply it.");
    }
}
