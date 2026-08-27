<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\ConditionalArraySpread;
use JesseGall\CodeCommandments\Testing\Fixed;
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

/**
 * The same descriptor assembled through a null-dropping variadic factory: every optional key is a
 * named argument, and an absent one never reaches the array — no ternary, no empty spread.
 */
final class Descriptor
{
    public function __construct(
        public readonly string $key,
        public readonly ?string $label = null,
        public readonly ?string $icon = null,
    ) {}

    /**
     * @return array{key: string, label?: string, icon?: string}
     */
    #[Fixed(ConditionalArraySpread::class)]
    public function toArray(): array
    {
        return Payload::of(key: $this->key, label: $this->label, icon: $this->icon);
    }
}

/**
 * Builds a payload from named arguments, dropping the ones that carry no value.
 */
final class Payload
{
    /**
     * @return array<string, mixed>
     */
    #[Fixed(ConditionalArraySpread::class)]
    public static function of(mixed ...$values): array
    {
        $payload = [];

        foreach ($values as $name => $value) {
            if ($value !== null) {
                $payload[$name] = $value;
            }
        }

        return $payload;
    }
}
