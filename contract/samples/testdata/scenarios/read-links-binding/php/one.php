<?php

function doubled(
    int $total, // @label total binding
): int {
    return $total * 2; // @contract promise/read-links-binding -> total
}
