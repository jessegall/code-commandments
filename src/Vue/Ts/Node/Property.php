<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A property signature — `a: T`, `a?: T`. Its {@see type} is its annotation as-is.
 */
final class Property extends Member
{
    public function __construct(
        string $name,
        private readonly TypeNode $annotation,
        bool $optional = false,
    ) {
        parent::__construct($name, $optional);
    }

    public function type(): TypeNode
    {
        return $this->annotation;
    }

    public function render(): string
    {
        return $this->name . ($this->optional ? '?' : '') . ': ' . $this->annotation->render();
    }
}
