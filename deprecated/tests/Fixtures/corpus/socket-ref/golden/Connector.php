<?php

namespace App\SocketRef;

/**
 * Wires two declared sockets together once the pairing is validated.
 */
final class Connector
{
    public function __construct(
        private readonly ConnectionValidator $validator,
        private readonly SocketRegistry $sockets,
    ) {}

    public function connect(SocketRef $source, SocketRef $target): Connection
    {
        if (! $this->validator->isValid($source, $target)) {
            throw InvalidConnectionException::between($source, $target);
        }

        return new Connection(
            source: $this->sockets->get($source),
            target: $this->sockets->get($target),
        );
    }
}
