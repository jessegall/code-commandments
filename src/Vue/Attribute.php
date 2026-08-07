<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use JesseGall\PhpTypes\Option;

/**
 * One attribute of a template element: its name, and the value a valueless attribute
 * (`v-else`, `setup`) genuinely does not have. The pair used to travel as a
 * `array<string, string|null>` map that every writer re-rendered by hand, so the
 * rendering lives here — one place decides what `v-else` and `v-if="ok"` look like.
 */
final readonly class Attribute
{
    /**
     * @param  Option<string>  $value
     */
    public function __construct(
        public string $name,
        public Option $value,
    ) {}

    /**
     * The attribute as it is written in a tag — a valueless one is its bare name.
     */
    public function render(): string
    {
        return $this->value->mapOr($this->name, fn (string $value): string => "{$this->name}=\"{$value}\"");
    }

    /**
     * A run of attributes as it is written inside a tag, space-separated.
     *
     * @param  list<self>  $attributes
     */
    public static function renderAll(array $attributes): string
    {
        return implode(' ', array_map(static fn (self $attribute): string => $attribute->render(), $attributes));
    }

    /**
     * Are any of these the named attribute? The question a caller used to ask by keying
     * into the carried map, which is the element's own business, not the caller's.
     *
     * @param  list<self>  $attributes
     */
    public static function anyNamed(array $attributes, string|Directive $name): bool
    {
        $key = Directive::attributeName($name);

        foreach ($attributes as $attribute) {
            if ($attribute->name === $key) {
                return true;
            }
        }

        return false;
    }
}
