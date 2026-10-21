<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\UnreadCollaborator;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Shelving\SlugText;

/**
 * Makes the slug a product is found by: the name in lower case, every run of anything that
 * is not a letter or a digit turned into one dash, the id on the end so two products of the
 * same name stay apart. The slug text the constructor asks for is read nowhere.
 */
final class ProductSlugger
{
    #[Sinful(UnreadCollaborator::class)]
    public function __construct(private readonly SlugText $slugs) {}

    public function slugFor(string $name, int $id): string
    {
        $words = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));

        return trim((string) $words, '-') . '-' . $id;
    }
}
