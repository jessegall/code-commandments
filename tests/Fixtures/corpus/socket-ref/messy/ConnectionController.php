<?php

namespace App\SocketRef;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Accepts a connect request and explodes each endpoint into a loose (node, socket, direction) triple.
 */
final class ConnectionController
{
    public function __construct(
        private readonly Connector $connector,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        // The dotted shorthand "node.socket" gets split into loose strings right
        // at the door, and the direction is a bare string literal, not a type.
        $from = explode('.', (string) $request->input('from'), 2);
        $to = explode('.', (string) $request->input('to'), 2);

        $fromNodeId = $from[0] ?? '';
        $fromSocketId = $from[1] ?? '';
        $toNodeId = $to[0] ?? '';
        $toSocketId = $to[1] ?? '';

        $connection = $this->connector->connect(
            $fromNodeId,
            $fromSocketId,
            'output',
            $toNodeId,
            $toSocketId,
            'input',
        );

        // And to build the redirect key we re-clump the strings by hand, again.
        $key = $connection['from_node'] . ':' . $connection['from_socket'] . ':' . $connection['from_direction'];

        return redirect()->route('graph.show', ['socket' => $key]);
    }
}
