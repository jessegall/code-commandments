<?php

namespace Shop\Dispatch;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\HandedOutCollection;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The crates one manifest lists. A Collection is an object, so handing the manifest's own out lets every caller
 * push onto it, forget from it and sort it without the manifest knowing. Below it, the FIX: a copy goes out, and
 * the manifest grew the method the callers were doing by hand.
 */
final class CrateManifest
{
    private Collection $crates;

    public function __construct()
    {
        $this->crates = collect();
    }

    #[Sinful(HandedOutCollection::class)]
    public function crates(): Collection
    {
        return $this->crates;
    }

    #[Fixed(HandedOutCollection::class)]
    public function listed(): Collection
    {
        return $this->crates->collect();
    }

    #[Fixed(HandedOutCollection::class)]
    public function add(Crate $crate): void
    {
        $this->crates->push($crate);
    }

    public function heaviest(): ?Crate
    {
        $sorted = $this->crates->sortByDesc(static fn (Crate $crate) => $crate->reference);

        return $sorted->first();
    }

    public function references(): string
    {
        return $this->crates->map(static fn (Crate $crate) => $crate->reference)->join(', ');
    }
}
