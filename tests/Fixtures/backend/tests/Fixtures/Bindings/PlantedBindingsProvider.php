<?php

namespace Shop\Tests\Fixtures\Bindings;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Legacy\FeedImporter;

final class PlantedBindingsProvider extends ServiceProvider
{
    #[Righteous(OrphanedBinding::class)]
    public function register(): void
    {
        App::singleton(FeedImporter::class);
    }
}
