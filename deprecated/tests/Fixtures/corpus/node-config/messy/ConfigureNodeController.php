<?php

namespace App\NodeConfig;

use Illuminate\Http\RedirectResponse;

/**
 * Threads the raw editor config array straight through to every collaborator.
 */
final class ConfigureNodeController
{
    public function __construct(
        private readonly NodeExpander $expander,
        private readonly NodeValidator $validator,
        private readonly NodePresenter $presenter,
    ) {}

    public function store(ConfigureNodeRequest $request): RedirectResponse
    {
        // ROOT SMELL: the loose config bag is never parsed into a type at the
        // boundary — the same untyped array is handed to three collaborators,
        // each of which re-reads the same keys with its own coalesce defaults.
        $config = $request->input('config', []);
        $nodeId = $request->input('node_id');

        $errors = $this->validator->violations($config);

        if (count($errors) > 0) {
            return redirect()
                ->route('editor.node.edit', $nodeId)
                ->withErrors($errors);
        }

        $expanded = $this->expander->expand($nodeId, $config);

        return redirect()
            ->route('editor.node.show', $expanded['id'])
            ->with('status', $this->presenter->summary($config));
    }
}
