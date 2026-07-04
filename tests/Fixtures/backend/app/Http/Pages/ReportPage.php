<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Http\Requests\ReportRequest;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * The righteous page-object shape, taken from the real Smart Farmers pattern: the request is injected
 * `#[Hidden]` (so it never reaches the frontend type), every slot is a `#[Computed]` hook seeded from
 * it, and the heavy list is deferred with `Lazy`. Nothing leaks, nothing is orchestrated in a
 * constructor — the InjectedServiceNotHidden detector must NOT flag it.
 */
#[Righteous(InjectedServiceNotHidden::class)]
final class ReportPage extends Data
{
    #[Computed]
    public string $timeRange { get => $this->request->timeRange(); }

    #[Computed]
    public MenuLink $primaryAction { get => new MenuLink('Export', '/export'); }

    /** @var list<StatCard>|Lazy */
    #[Computed]
    #[DataCollectionOf(StatCard::class)]
    public array|Lazy $statistics { get => Lazy::closure(fn (): array => []); }

    public function __construct(
        #[Hidden]
        #[FromContainer(ReportRequest::class)]
        public readonly ReportRequest $request,
    ) {}
}
