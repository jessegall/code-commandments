<?php

namespace App\SocketRef;

/**
 * Which side of a node a socket sits on, and therefore which end of a wire it can be.
 */
enum Direction: string
{
    /** A socket that receives a wire — the destination end of a connection. */
    case Input = 'input';

    /** A socket that emits a wire — the source end of a connection. */
    case Output = 'output';

    public function isSource(): bool
    {
        return match ($this) {
            Direction::Output => true,
            Direction::Input => false,
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            Direction::Input => Direction::Output,
            Direction::Output => Direction::Input,
        };
    }

    public function label(): string
    {
        return match ($this) {
            Direction::Input => 'Input',
            Direction::Output => 'Output',
        };
    }
}
