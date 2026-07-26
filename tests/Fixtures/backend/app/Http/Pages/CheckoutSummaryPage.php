<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PageObjectMissingTypeScript;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Data;

/**
 * Everything promoted in the constructor — the data slots AND the injected reader side by side. The
 * `$cart` collaborator is public and un-hidden, so it ships to the frontend type with the payload.
 */
#[Sinful(InjectedServiceNotHidden::class)]
#[Sinful(PageObjectMissingTypeScript::class)]
final class CheckoutSummaryPage extends Data
{
    #[Computed]
    public int $itemCount { get => count($this->lines); }

    public function __construct(
        public readonly CartLine $lead,
        public readonly StatCard $total,
        /** @var list<CartLine> */
        #[DataCollectionOf(CartLine::class)]
        public readonly array $lines,
        #[FromContainer(CartReader::class)]
        public readonly CartReader $cart,
    ) {}

    public function isBackordered(): bool
    {
        return $this->lead->qty > 99;
    }
}
