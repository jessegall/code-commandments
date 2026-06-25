<?php

namespace App\EditorMetadata;

use Illuminate\Http\RedirectResponse;

/**
 * MCP/editor action that applies a set-node-metadata patch.
 *
 * SYMPTOM: the controller hand-builds the untyped payload from the array-of-
 * mixed patch (string-indexing each slot) and then has to itself re-coerce the
 * `value` for the redirect, because nothing between the request and here ever
 * gave `value` a type.
 */
class SetNodeMetadataController
{
    public function __construct(
        private readonly MetadataHandler $handler,
    ) {}

    public function store(SetNodeMetadataRequest $request): RedirectResponse
    {
        $patch = $request->patch();

        $payload = new SetNodeMetadataPayload(
            nodeId: (string) $patch['node_id'],
            aspect: (string) $patch['aspect'],
            value: $patch['value'],
        );

        $this->handler->applyAspect($payload);

        // coping at yet another site: is the value a single label we can show?
        $value = $payload->value;
        $hint = is_string($value)
            ? $value
            : (is_array($value) ? (string) count($value) . ' items' : 'updated');

        return redirect()
            ->route('editor.nodes.show', $payload->nodeId)
            ->with('status', "Set {$payload->aspect}: {$hint}");
    }
}
