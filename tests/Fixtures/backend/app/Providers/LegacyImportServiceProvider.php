<?php

namespace Shop\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Legacy\FeedImporter;

/**
 * Registered through the facade rather than `$this->app`, which changes nothing: nothing has asked
 * for a FeedImporter since the legacy feed was retired, so the scope this opens is never entered.
 */
class LegacyImportServiceProvider extends ServiceProvider
{
    #[Sinful(OrphanedBinding::class)]
    public function register(): void
    {
        App::scoped(FeedImporter::class);
    }
}
