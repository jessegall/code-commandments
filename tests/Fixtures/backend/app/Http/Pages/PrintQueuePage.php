<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PageObjectMissingTypeScript;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ServiceLocationInPageObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * The Smart Farmers PrintAgents IndexPage shape — seeded through a `for()` factory over direct nested
 * slots, reading a buffer service out of the container inside a getter.
 */
#[Sinful(PageObjectMissingTypeScript::class)]
final class PrintQueuePage extends Data
{
    public readonly CartLine $current;

    public readonly MenuLink $home;

    public readonly StatCard $depth;

    public static function for(string $agent): self
    {
        return self::from(['agent' => $agent]);
    }

    #[Sinful(ServiceLocationInPageObject::class)]
    #[Sinful(ContainerReach::class)]
    public function bufferSize(): int
    {
        return app(LogBuffer::class)->size();
    }

    public function summary(): string
    {
        return sprintf('%s: %d queued at %s', $this->current->sku, $this->current->qty, $this->home->label);
    }
}
