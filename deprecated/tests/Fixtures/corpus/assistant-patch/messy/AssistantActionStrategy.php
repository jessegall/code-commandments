<?php

namespace App\AssistantPatch;

/**
 * Decodes one raw action type into its typed AssistantAction.
 */
interface AssistantActionStrategy
{
    public function decode(RawAssistantAction $raw): AssistantAction;
}
