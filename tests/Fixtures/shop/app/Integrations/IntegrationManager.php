<?php

namespace Shop\Integrations;

/**
 * Base manager declaring the `config(): array` contract subclasses must fulfil.
 */
abstract class IntegrationManager
{
    abstract public function config(): array;
}
