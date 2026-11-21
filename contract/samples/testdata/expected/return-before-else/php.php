<?php

function sign(int $n): string
{
    if ($n < 0) {
        return 'negative';
    } else {
        return 'positive';
    }
}
