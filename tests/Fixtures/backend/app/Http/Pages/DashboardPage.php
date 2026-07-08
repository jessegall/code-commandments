<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PageObjectMissingTypeScript;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Data;

/**
 * Two direct stat slots; one container-injected reporter left PUBLIC and un-hidden — it serializes
 * and leaks into the frontend `DashboardPage` type.
 */
#[Sinful(InjectedServiceNotHidden::class)]
#[Sinful(PageObjectMissingTypeScript::class)]
final class DashboardPage extends Data
{
    public readonly StatCard $revenue;

    public readonly StatCard $orders;

    public function __construct(
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {}

    public function headline(): string
    {
        return $this->revenue->label;
    }

    public function ordersValue(): string
    {
        return $this->orders->value;
    }

    public function summary(): string
    {
        return $this->headline() . ' | ' . $this->ordersValue();
    }
}
