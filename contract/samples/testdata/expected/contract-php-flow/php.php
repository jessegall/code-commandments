<?php

// @contract fact/function/generator = true
function lines(string $path): iterable
{
    // @contract kind/try
    // @contract role/try/body -> tryBody
    // @contract role/try/finally -> finallyBody
    // @label tryBody block
    try {
        // @contract kind/yield
        // @contract fact/yield/delegates = true
        // @contract role/yield/value -> yieldedCall
        // @label yieldedCall call
        yield from file($path);
    // @label finallyBody block
    } finally {
        echo 'closed';
    }
}
