<?php

namespace App\NodeKind;

use Illuminate\Support\Fluent;

/**
 * MCP/editor action that drops a node and previews it — wiring all three drifted
 * ladders together AND re-checking the kind string with its own literal compares.
 */
class WorkflowEditorController
{
    public function __construct(
        private NodeFactory $factory,
        private NodeRenderer $renderer,
        private NodeValidator $validator,
    ) {}

    public function addNode(Fluent $request): array
    {
        $kind = $request->get('kind') ?? 'action';

        // re-validate the string here too, because nothing upstream is typed
        if ($kind !== 'trigger' && $kind !== 'action' && $kind !== 'condition') {
            return ['error' => "Unknown node kind {$kind}"];
        }

        $node = $this->factory->make($kind);

        return [
            'node' => $node->toArray(),
            'glyph' => $this->renderer->glyph($node),
            'caption' => $this->renderer->caption($node),
            'missing' => $this->validator->missingConfig($node),
        ];
    }
}
