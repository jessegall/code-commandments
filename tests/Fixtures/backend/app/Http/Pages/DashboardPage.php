<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PageObjectMissingTypeScript;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

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

/**
 * The FIX for the same dashboard: `#[TypeScript]` on the page object, so the transformer generates the
 * frontend type the `.vue` page binds its props against — the payload contract is checked, not `any`.
 * (The reporter is injected `#[Hidden]`, so only the page data reaches that type.)
 */
#[TypeScript]
#[Fixed(PageObjectMissingTypeScript::class)]
final class TypedDashboardPage extends Data
{
    public readonly StatCard $revenue;

    public readonly StatCard $orders;

    public function __construct(
        #[Hidden]
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {}

    public function caption(): string
    {
        return $this->revenue->label . ' / ' . $this->orders->value;
    }
}
