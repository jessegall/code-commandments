<?php

namespace Toy;

function batches(int $total, int $size): array
{
    $batches = [];

    for ($offset = 0; $offset < $total; $offset += $size) {
        // @sin LoopedNew
        $batches[] = new \ArrayObject(range($offset, min($total, $offset + $size) - 1));
    }

    return $batches;
}
