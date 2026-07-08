<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Testing\Righteous;

/*
 * Righteous twin for RepeatedTypeGuard: a multi-`instanceof` narrowing used exactly ONCE. A one-off guard
 * is fine — only a guard copied verbatim into ≥2 places wants a name. Must NOT flag.
 */
final class SingleTypeCheck
{
    #[Righteous(RepeatedTypeGuard::class)]
    public function accepts($n): bool
    {
        return $n instanceof Element && $n->attribute instanceof Marker;
    }
}
