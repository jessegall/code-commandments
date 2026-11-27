<?php

namespace Shop\Absences;

/**
 * Refuses a request that carries no trace where one is required.
 */
final class MissingTrace extends \RuntimeException
{
    public function __construct(public readonly string $header)
    {
        parent::__construct("The request carries no {$header} header.");
    }
}
