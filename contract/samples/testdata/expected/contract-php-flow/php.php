<?php

function lines(string $path): iterable
{
    try {
        yield from file($path);
    } finally {
        echo 'closed';
    }
}
