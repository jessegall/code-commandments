<?php

namespace Shop\Providers;

use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Audit\AuditTrail;

/**
 * A self-building singleton. The factory names AuditTrail twice, but both mentions are the
 * registration talking to itself — nothing outside ever asks the container for one.
 */
class AuditServiceProvider extends ServiceProvider
{
    private const string CHANNEL = 'shop';

    #[Sinful(OrphanedBinding::class)]
    public function register(): void
    {
        $channel = self::CHANNEL;

        $this->app->singleton(AuditTrail::class, static function () use ($channel): AuditTrail {
            $trail = new AuditTrail($channel);

            $trail->record('booted');

            return $trail;
        });
    }
}
