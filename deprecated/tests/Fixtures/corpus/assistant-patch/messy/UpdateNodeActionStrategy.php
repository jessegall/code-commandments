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

        // ROOT SMELL: `id` and `summary` are REQUIRED, non-nullable strings on the
        // action, but `$raw->id`/`$raw->summary` are nullable. Each is coalesced to
        // its type's empty literal ('') so it slips past the required type — a fake
        // value, not a real one (an action with id '' has no identity). Note nodeId,
        // asserted above, is handled honestly; id deserves the same guard, and
        // summary — if truly optional — belongs as a nullable field on the type.
        return AssistantUpdateNodeAction::from([
            'id' => $raw->id ?? T_String::empty(),
            'summary' => $raw->summary ?? T_String::empty(),
            'nodeId' => $raw->nodeId,
            self::KEY_NAME => $name,
            self::KEY_INPUTS => $inputs,
            self::KEY_OUTPUTS => $outputs,
        ]);
    }

    /**
     * Only nodeId is guarded here — the asymmetry is the point: nodeId is rejected
     * when absent, while id/summary get silently zero-filled above.
     */
    private function assertUsable(RawAssistantAction $raw, bool $hasChange): void
    {
        if ($raw->nodeId === null || T_String::isEmpty($raw->nodeId) || ! $hasChange)
        {
            throw UnusableAssistantActionException::unusablePayload(AssistantActionType::UpdateNode);
        }
    }

}
