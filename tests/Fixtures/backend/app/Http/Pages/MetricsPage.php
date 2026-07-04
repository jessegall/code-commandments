<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ConstructorOrchestration;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;

/**
 * Slots including a typed collection, all filled straight from the injected builder in the
 * constructor — each a self-contained projection that a `#[Computed]` hook would carry.
 */
final class MetricsPage extends Data
{
    public readonly StatCard $headline;

    /** @var list<StatCard> */
    #[DataCollectionOf(StatCard::class)]
    public readonly array $cards;

    #[Sinful(ConstructorOrchestration::class)]
    public function __construct(
        #[Hidden]
        #[FromContainer(FacetBuilder::class)]
        public readonly FacetBuilder $builder,
    ) {
        $this->headline = $this->builder->headline();
        $this->cards = $this->builder->cards();
    }

    public function caption(): string
    {
        return sprintf('%s across %d metrics', $this->headline->label, count($this->cards));
    }

    public function labels(): string
    {
        $names = [];

        foreach ($this->cards as $card) {
            $names[] = strtoupper($card->label);
        }

        return implode(', ', $names);
    }
}
