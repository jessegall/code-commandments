<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ServiceLocationInPageObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The courier board a dispatcher watches: the next pickup and the link home, built into the
 * controller's props and rendered from there.
 */
#[TypeScript]
final class CourierBoardPage extends Data
{
    public readonly CartLine $nextPickup;

    public readonly MenuLink $home;

    #[Sinful(ServiceLocationInPageObject::class)]
    #[Sinful(ContainerReach::class)]
    public function backlog(): int
    {
        return app(LogBuffer::class)->size();
    }
}
