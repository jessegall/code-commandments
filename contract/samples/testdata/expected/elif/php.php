<?php

function size(int $n): string
{
    if ($n < 10) {
        return 'small';
    } elseif ($n < 100) {
        return 'medium';
    } else {
        return 'large';
    }
}
