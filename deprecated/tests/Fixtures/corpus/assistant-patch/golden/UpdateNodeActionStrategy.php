<?php

namespace App\AssistantPatch;

use JesseGall\PhpTypes\T_Array;
use JesseGall\PhpTypes\T_String;

/**
 * Decodes an `update_node` action into a partial node patch (name / inputs / outputs).
 */
final class UpdateNodeActionStrategy implements AssistantActionStrategy
{

    private const KEY_NAME = 'name';

    private const KEY_INPUTS = 'inputs';

    private const KEY_OUTPUTS = 'outputs';

    public function decode(RawAssistantAction $raw): AssistantAction
    {
        $node = $raw->node;
        $name = $node?->name;
        $inputs = $node !== null ? $node->inputs : T_Array::empty();
        $outputs = $node !== null ? $node->outputs : T_Array::empty();

        $this->assertUsable($raw, $name !== null || T_Array::isNotEmpty($inputs) || T_Array::isNotEmpty($outputs));

        // No coalescing-to-empty: id/nodeId are now known non-empty, and summary
        // flows through as the nullable it genuinely is.
        return AssistantUpdateNodeAction::from([
            'id' => $raw->id,
            'summary' => $raw->summary,
            'nodeId' => $raw->nodeId,
            self::KEY_NAME => $name,
            self::KEY_INPUTS => $inputs,
            self::KEY_OUTPUTS => $outputs,
        ]);
    }

    /**
     * The required identity is asserted at the boundary: a missing or empty
     * id/nodeId (or no actual change) makes the action unusable and is rejected
     * here, rather than smuggled through as ''.
     */
    private function assertUsable(RawAssistantAction $raw, bool $hasChange): void
    {
        if ($raw->id === null || T_String::isEmpty($raw->id)
            || $raw->nodeId === null || T_String::isEmpty($raw->nodeId)
            || ! $hasChange)
        {
            throw UnusableAssistantActionException::unusablePayload(AssistantActionType::UpdateNode);
        }
    }

}
