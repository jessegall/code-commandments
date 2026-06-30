<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * A hit from the {@see ComponentLibrary}: an existing component that fits an
 * extraction, so the scribe references it (`<UserCard :user="order.customer" />` +
 * import) instead of writing a new file. {@see $bindings} maps the component's prop
 * name to the object expression the boundary passes it.
 */
final class ComponentReuse
{
    /**
     * @param  array<string, string>  $bindings  prop name => the expression to bind
     */
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly array $bindings,
    ) {}
}
