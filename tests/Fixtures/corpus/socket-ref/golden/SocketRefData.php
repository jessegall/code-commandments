<?php

namespace App\SocketRef;

use Illuminate\Support\Fluent;

/**
 * The deserialization boundary: one socket endpoint as it arrives over the wire
 * (a `"nodeId.socketId"` shorthand OR an attribute array) coalesced into a single
 * total bag, then handed downstream ONLY as a typed SocketRef.
 */
final class SocketRefData extends Fluent
{
    /**
     * Total factory: a dotted-string shorthand becomes its node/socket pair on the
     * given side; anything that is not an array becomes an empty endpoint. Never null.
     */
    public static function coalesce(mixed $endpoint, Direction $direction): self
    {
        if (is_string($endpoint)) {
            return self::fromShorthand($endpoint, $direction);
        }

        $bag = new self(is_array($endpoint) ? $endpoint : []);
        $bag->offsetSet('direction', $direction->value);

        return $bag;
    }

    private static function fromShorthand(string $endpoint, Direction $direction): self
    {
        $segments = collect(explode('.', $endpoint, 2));

        return new self([
            'node_id' => $segments->first(),
            'socket_id' => $segments->last(),
            'direction' => $direction->value,
        ]);
    }

    public function nodeId(): string
    {
        return $this->string('node_id');
    }

    public function socketId(): string
    {
        return $this->string('socket_id');
    }

    public function direction(): Direction
    {
        return Direction::from($this->string('direction'));
    }

    /** The single typed value the rest of the system threads around. */
    public function toRef(): SocketRef
    {
        return new SocketRef(
            nodeId: $this->nodeId(),
            socketId: $this->socketId(),
            direction: $this->direction(),
        );
    }
}
