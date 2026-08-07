<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Packages;

use InvalidArgumentException;

/**
 * A `#[Exempt(...)]` tag naming something that is not an exemption — a misspelt slug, or a class
 * that does not extend {@see Exemption}. Named so a caller can catch this and only this, and so the
 * sentence the author reads is written once, here, from the values that produced it.
 */
final class UnknownExemption extends InvalidArgumentException
{
    /**
     * @param  list<string>  $known  the slugs that WOULD have been accepted
     */
    public static function for(string $tagOrSlug, array $known): self
    {
        return new self(sprintf(
            '"%s" is not an exemption — pass an %s subclass or a known slug (%s).',
            $tagOrSlug,
            Exemption::class,
            implode(', ', $known),
        ));
    }
}
