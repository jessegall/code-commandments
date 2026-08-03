<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A page seeded through a `for()` factory, composing direct nested Data slots (no typed collection),
 * with TWO container-injected collaborators: `$reader` is correctly `#[Hidden]`, but `$facetBuilder`
 * is not — one un-hidden service is enough to leak into the frontend type.
 */
#[TypeScript]
#[Sinful(InjectedServiceNotHidden::class)]
final class CatalogPage extends Data
{
    public readonly MenuLink $home;

    public readonly MenuLink $active;

    public readonly CartLine $featured;

    public static function for(string $category): self
    {
        return self::from(['category' => $category]);
    }

    public function __construct(
        #[Hidden]
        #[FromContainer(CatalogReader::class)]
        public readonly CatalogReader $reader,

        #[FromContainer(FacetBuilder::class)]
        public readonly FacetBuilder $facetBuilder,

        public readonly string $category,
    ) {}

    public function breadcrumb(): string
    {
        return $this->home->label . ' / ' . $this->active->label;
    }
}

/**
 * The FIX for the same catalog page: BOTH injected collaborators carry `#[Hidden]`, so neither the
 * reader nor the facet builder serializes into the payload or reaches the generated TypeScript type.
 */
#[TypeScript]
#[Fixed(InjectedServiceNotHidden::class)]
final class HiddenCatalogPage extends Data
{
    public readonly MenuLink $home;

    public readonly MenuLink $active;

    public readonly CartLine $featured;

    public static function for(string $category): self
    {
        return self::from(['category' => $category]);
    }

    public function __construct(
        #[Hidden]
        #[FromContainer(CatalogReader::class)]
        public readonly CatalogReader $reader,

        #[Hidden]
        #[FromContainer(FacetBuilder::class)]
        public readonly FacetBuilder $facetBuilder,

        public readonly string $category,
    ) {}

    public function trail(): string
    {
        return $this->category . ': ' . $this->active->label;
    }
}
