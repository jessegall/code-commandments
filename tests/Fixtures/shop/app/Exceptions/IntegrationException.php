<?php

namespace Shop\Exceptions;

use RuntimeException;

final class IntegrationException extends RuntimeException
{
    public static function forService(string $service): self
    {
        return new self("The {$service} integration failed.");
    }
}
