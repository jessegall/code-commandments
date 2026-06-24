<?php

namespace App\SocketRef;

use Illuminate\Http\RedirectResponse;

/**
 * Accepts a connect request and redirects to the updated graph view.
 */
final class ConnectionController
{
    public function __construct(
        private readonly Connector $connector,
    ) {}

    public function store(ConnectRequest $request): RedirectResponse
    {
        $connection = $this->connector->connect($request->source(), $request->target());

        return redirect()->route('graph.show', ['socket' => $connection->source->key()]);
    }
}
