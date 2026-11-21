<?php

function slug(string $title): string
{
    return $title |> trim(...) |> strtolower(...);
}
