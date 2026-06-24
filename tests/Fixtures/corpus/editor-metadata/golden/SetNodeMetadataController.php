<?php

namespace App\EditorMetadata;

use Illuminate\Http\RedirectResponse;

/**
 * Accepts the editor action that sets a node's branches.
 */
final class SetNodeMetadataController
{
    public function __construct(
        private readonly BranchMetadataHandler $handler,
    ) {}

    public function store(SetNodeMetadataRequest $request): RedirectResponse
    {
        $payload = SetBranchesPayload::from(
            $request->nodeId(),
            $request->branches(),
        );

        $this->handler->apply($payload);

        return redirect()->route('editor.nodes.show', $payload->nodeId);
    }
}
