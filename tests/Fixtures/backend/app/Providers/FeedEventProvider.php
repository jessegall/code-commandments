<?php

namespace Shop\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Events\FeedImported;

/**
 * A closure listener reads as more alive than a class one — there is real logic in front of you — but
 * the event it waits for is never raised, so none of this code can run.
 */
class FeedEventProvider extends ServiceProvider
{
    private int $handled = 0;

    #[Sinful(DeadEventWiring::class)]
    public function boot(): void
    {
        Event::listen(FeedImported::class, function (FeedImported $event): void {
            $this->handled += $event->rows;
        });
    }

    public function handled(): int
    {
        return $this->handled;
    }
}
