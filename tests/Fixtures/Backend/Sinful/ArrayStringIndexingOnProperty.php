<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful;

/**
 * An untyped `$this->config` array pulled from somewhere, read via string keys.
 * The property should be typed as a DTO.
 */
class ArrayStringIndexingOnProperty
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function label(): string
    {
        return $this->config['label'];
    }

    public function icon(): string
    {
        return $this->config['icon'];
    }

    public function group(): string
    {
        return $this->config['group'];
    }
}
