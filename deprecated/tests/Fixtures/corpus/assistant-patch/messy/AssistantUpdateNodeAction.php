<?php

namespace App\AssistantPatch;

use JesseGall\PhpTypes\T_Array;

/**
 * A proposed patch to one existing node — `id`, `summary` and `nodeId` are all
 * REQUIRED, non-nullable: an update without them is not applicable.
 */
final class AssistantUpdateNodeAction extends AssistantAction
{
    /**
     * @param  list<mixed>  $inputs
     * @param  list<mixed>  $outputs
     */
    public function __construct(
        string $id,
        public readonly string $summary,
        public readonly string $nodeId,
        public readonly ?string $name = null,
        public readonly array $inputs = T_Array::EMPTY,
        public readonly array $outputs = T_Array::EMPTY,
    ) {
        parent::__construct(AssistantActionType::UpdateNode, $id);
    }
}
