<?php

// @contract kind/function
function good(): int
{
    return 1;
}

function broken(): int
{
    return 1 + ;
}
