<?php

function route(int $code): string
{
    switch ($code) {
        case 1:
        case 2:
            return 'low';
        default:
            return 'other';
    }
}

function band(int $code): string
{
    return match ($code) {
        1, 2 => 'low',
        default => 'other',
    };
}
