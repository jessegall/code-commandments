<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Bridge;

use JesseGall\CodeCommandments\Support\Name;

/**
 * A field one engine asks `=== ''` about, and the TYPE it asks it of — the question that says a
 * blank string is READ as "missing" rather than as the empty value. Published so the other engine
 * can pair it with the declaration that made the blank.
 */
final class BlanknessQuestion implements Contract
{
    /**
     * @param  string  $type  the type the asker held — the two sides share no code, so the NAME of
     *         the shape is the whole of the proof that they mean the same field
     */
    public function __construct(
        public readonly string $type,
        public readonly string $field,
    ) {}

    /**
     * Is this the question asked of $type's $field, each spelled however the other side spells it?
     */
    public function askedOf(string $type, string $field): bool
    {
        return Name::canonical($type) === Name::canonical($this->type)
            && Name::canonical($field) === Name::canonical($this->field);
    }
}
