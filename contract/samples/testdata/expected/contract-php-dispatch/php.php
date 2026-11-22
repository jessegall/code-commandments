<?php

function route(int $code): string
{
    // @contract kind/dispatch
    // @contract fact/dispatch/form = "switch"
    // @contract role/dispatch/subject -> switchSubject
    // @contract role/dispatch/arms -> [armOne, armTwo, armDefault]
    // @label switchSubject reference
    switch ($code) {
        // @label armOne arm
        // @contract kind/arm
        // @contract fact/arm/fallsThrough = true
        // @contract role/arm/patterns -> [patOne]
        // @contract kind/pattern
        // @contract fact/pattern/form = "value"
        // @contract role/pattern/value -> patValue
        // @label patOne pattern
        // @label patValue literal
        case 1:
        // @label armTwo arm
        case 2:
            return 'low';
        // @label armDefault arm
        // @contract fact/arm/default = true
        default:
            return 'other';
    }
}

function band(int $code): string
{
    // @contract kind/dispatch
    // @contract fact/dispatch/form = "match"
    // @contract role/dispatch/subject -> matchSubject
    // @contract role/dispatch/arms -> [matchLow, matchDefault]
    // @label matchSubject reference
    return match ($code) {
        // @label matchLow arm
        // @contract role/arm/patterns -> [pOne, pTwo]
        // @contract role/arm/body -> lowBody
        // @label pOne pattern 9
        // @label pTwo pattern 12
        // @label lowBody literal 17
        1, 2 => 'low',
        // @label matchDefault arm
        // @contract fact/arm/default = true
        default => 'other',
    };
}
