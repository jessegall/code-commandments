<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataCollectionType;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/*
 * Scenario 3 — a kanban column typed `DataCollection`, coloured by lane through a match.
 */
#[Sinful(DataCollectionType::class)]
final class BoardColumn extends Data
{
    /**
     * @var DataCollection<int, Card>
     */
    public function __construct(
        public readonly string $lane,
        public readonly DataCollection $cards,
    ) {}

    public function accent(): string
    {
        return match ($this->lane) {
            'blocked' => 'red',
            'review' => 'amber',
            'done' => 'green',
            default => 'slate',
        };
    }

    public function slug(): string
    {
        return str_replace(' ', '-', strtolower($this->lane));
    }
}

final class Card extends Data
{
    public function __construct(public readonly string $title) {}
}
