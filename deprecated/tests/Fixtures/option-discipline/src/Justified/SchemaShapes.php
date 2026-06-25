<?php
namespace Acme\Notify\Justified;
use JesseGall\PhpTypes\Option;

final class SchemaShapes
{
    /** @var array<string, array<string, mixed>> */
    private array $known = [];

    // Genuine value-or-nothing: a real none() path. Callers unwrap/branch on it —
    // which is NORMAL. The retired smell #3 wrongly flagged this; we must stay silent.
    public function shape(string $slug): Option
    {
        if (! isset($this->known[$slug])) {
            return Option::none();
        }
        return Option::some($this->known[$slug]);
    }
}
