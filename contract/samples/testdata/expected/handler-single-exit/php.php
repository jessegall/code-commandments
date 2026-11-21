<?php

function load(): ?string
{
    try {
        return read();
    } catch (\RuntimeException $e) {
        return null;
    }
}

function each(array $paths): void
{
    foreach ($paths as $path) {
        try {
            read();
        } catch (\RuntimeException $e) {
            continue;
        }
    }
}
