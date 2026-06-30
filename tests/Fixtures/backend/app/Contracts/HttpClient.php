<?php

namespace Shop\Contracts;

/**
 * The transport over which we talk to external APIs — returns the raw response body.
 */
interface HttpClient
{
    public function get(string $url): string;
}
