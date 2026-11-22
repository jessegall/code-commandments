<?php

function counter(int $step): callable
{
    global $total;
    static $calls = 0;
    ;

    return function (int $by) use ($step) {
        return $by + $step;
    };
}
