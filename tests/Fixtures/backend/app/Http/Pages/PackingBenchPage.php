<?php

namespace Shop\Http\Pages;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ServiceLocationInPageObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The packing bench: the lines waiting to be packed and the day's totals, each slot a collection whose
 * Data only its docblock names.
 */
#[TypeScript]
final class PackingBenchPage extends Data
{
    /**
     * @var Collection<int, CartLine>
     */
    public Collection $waiting;

    /**
     * @var Collection<int, StatCard>
     */
    public Collection $totals;

    #[Sinful(ServiceLocationInPageObject::class)]
    #[Sinful(ContainerReach::class)]
    public function backlog(): int
    {
        return app(LogBuffer::class)->size();
    }
}
