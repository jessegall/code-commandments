<?php

function flags(int &$count, string ...$names): ?int
{
    for ($i = 0; $i < 3; $i++) {
        $count++;
    }
    tally(label: 'a', ...$names);
    yield $count;

    return $count ?: null;
}
