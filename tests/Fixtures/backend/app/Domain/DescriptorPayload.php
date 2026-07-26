<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\ConditionalArraySpread;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * A serialiser that conditionally splices optional keys into its payload with a ternary-into-empty-array
 * spread. Each optional key is "include when present" noise a null-dropping `::of(...)` factory removes.
 */
final class DescriptorPayload
{
    public function __construct(
        public readonly string $key,
        public readonly ?string $label = null,
        public readonly ?string $icon = null,
    ) {}

    /**
     * @return array{key: string, label?: string, icon?: string}
     */
    #[Sinful(ConditionalArraySpread::class)]
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            ...($this->label !== null ? ['label' => $this->label] : []),
            ...($this->icon === null ? [] : ['icon' => $this->icon]),
        ];
    }
}
