<?php

namespace Shop\Http\Pages\Hydration;

/**
 * The docks a shell projects. It is a collaborator the page holds, not a field the wire carries —
 * which is the whole reason a hook reading it has to say so.
 */
final class DockSet
{
    /**
     * @param  list<string>  $docks
     */
    public function __construct(private readonly array $docks = []) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->docks;
    }
}
