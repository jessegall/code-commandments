<?php

namespace App\AssistantPatch;

/**
 * Discriminator for the assistant action union.
 */
enum AssistantActionType: string
{
    /** Patch one existing node's configuration (name / inputs / outputs). */
    case UpdateNode = 'update_node';
}
