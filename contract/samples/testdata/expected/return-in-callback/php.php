<?php

function visit(array $values): void
{
    // @contract kind/function
    array_walk($values, function ($value) {
        // @contract kind/return
        // @contract role/return/values -> []
        return;
    });
}
