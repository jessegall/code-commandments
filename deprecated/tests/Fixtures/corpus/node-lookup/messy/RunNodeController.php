<?php

namespace App\NodeLookup;

use Illuminate\Http\RedirectResponse;

// SYMPTOM of the nullable `find`: the controller double-checks existence the
// service already half-checked, coalescing a default kind so the redirect
// "can't" break — a guard that only exists because the lookup is partial.

/**
 * Starts a workflow run from the selected node and redirects to its monitor.
 */
final class RunNodeController
{
    public function __construct(
        private readonly NodeRunService $runs,
        private readonly NodeRepository $nodes,
    ) {}

    public function store(RunNodeRequest $request): RedirectResponse
    {
        $id = $request->nodeId();
        $node = $this->nodes->find($id);

        $kind = $node !== null ? $node->kind : NodeKind::Action;

        if ($node !== null && $node->canStartRun()) {
            $kind = $this->runs->startFrom($id);
        }

        return redirect()->route('runs.show', ['kind' => $kind->value]);
    }
}
