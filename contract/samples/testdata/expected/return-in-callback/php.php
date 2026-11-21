<?php

function visit(array $values): void
{
    array_walk($values, function ($value) {
        return;
    });
}
