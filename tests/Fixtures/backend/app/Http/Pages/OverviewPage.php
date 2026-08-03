<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ConstructorOrchestration;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The EditorShell shape — an assembly-line constructor that fills each public slot from a projector.
 * Every fill is self-contained (`$this->x = $this->sales->…()`), so each should be a `#[Computed]`
 * hook instead.
 */
#[TypeScript]
final class OverviewPage extends Data
{
    public readonly MenuLink $primary;

    public readonly MenuLink $secondary;

    public readonly CartLine $featured;

    #[Sinful(ConstructorOrchestration::class)]
    public function __construct(
        #[Hidden]
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {
        $this->primary = $this->sales->primaryLink();
        $this->secondary = $this->sales->secondaryLink();
        $this->featured = $this->sales->featuredLine();
    }

    public function activeLinks(): int
    {
        $count = 0;

        foreach ([$this->primary, $this->secondary] as $link) {
            if ($link->href !== '') {
                $count++;
            }
        }

        return $count;
    }
}

/**
 * The FIX for the same overview: every slot is a `#[Computed]` get-hook that projects itself from the
 * injected reporter, so the class declares WHAT each field is next to HOW it is projected — and the
 * constructor is left holding nothing but the seed.
 */
#[TypeScript]
#[Fixed(ConstructorOrchestration::class)]
final class ComputedOverviewPage extends Data
{
    #[Computed]
    public MenuLink $primary { get => $this->sales->primaryLink(); }

    #[Computed]
    public MenuLink $secondary { get => $this->sales->secondaryLink(); }

    #[Computed]
    public CartLine $featured { get => $this->sales->featuredLine(); }

    public function __construct(
        #[Hidden]
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {}
}
