<?php

namespace App\NodeLookup;

use Illuminate\Http\RedirectResponse;

/**
 * Starts a workflow run from the selected node and redirects to its monitor.
 */
final class RunNodeController
{
    public function __construct(
        private readonly NodeRunService $runs,
    ) {}

    public function store(RunNodeRequest $request): RedirectResponse
    {
        $kind = $this->runs->startFrom($request->nodeId());

        return redirect()->route('runs.show', ['kind' => $kind->value]);
    }
}
