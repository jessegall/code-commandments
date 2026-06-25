<?php

namespace App\NodeConfig;

use Illuminate\Http\RedirectResponse;

/**
 * Parses an editor node-config payload once, then expands, validates, and presents it.
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
        $config = NodeConfig::from($request->rawConfig());

        if (! $this->validator->passes($config)) {
            return redirect()
                ->route('editor.node.edit', $request->nodeId())
                ->withErrors($this->validator->violations($config)->all());
        }

        $expanded = $this->expander->expand($request->nodeId(), $config);

        return redirect()
            ->route('editor.node.show', $expanded->id)
            ->with('status', $this->presenter->summary($config));
    }
}
