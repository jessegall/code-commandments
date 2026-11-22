<?php

function scan(array $rows): int
{
    $seen = 0;
    foreach ($rows as $row) {
        foreach ($row as $cell) {
            if ($cell === null) {
                continue 2;
            }
            if ($cell === '') {
                break 2;
            }
            ++$seen;
        }
    }
    goto done;
    done:
    return $seen;
}
