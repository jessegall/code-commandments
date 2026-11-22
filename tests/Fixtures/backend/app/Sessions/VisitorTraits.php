<?php

namespace Shop\Sessions;

use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * What the shop remembers about a visitor between page views, keyed by the trait's name.
 */
final class VisitorTraits
{
    /**
     * @param  array<string, string>  $traits
     */
    public function __construct(private readonly array $traits) {}

    #[Sinful(NullableRegistryLookup::class)]
    public function trait(string $name): ?string
    {
        return isset($this->traits[$name]) ? $this->traits[$name] : null;
    }

    public function greeting(): string
    {
        return 'Hello '.$this->trait('first_name');
    }
}
