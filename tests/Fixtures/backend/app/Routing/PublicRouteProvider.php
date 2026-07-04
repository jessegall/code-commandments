<?php

namespace Shop\Routing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRoute;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The public storefront. The feed is bound twice under the same GET verb (sinful); the sitemap is bound
 * once (righteous — a single entry point).
 */
final class PublicRouteProvider extends ServiceProvider
{
    #[Sinful(DuplicateRoute::class)]
    public function feed(): void
    {
        Route::get('/feed', [FeedListController::class, 'list'])->name('feed');
        Route::get('/rss', [FeedListController::class, 'list'])->name('feed.rss');
    }

    #[Righteous(DuplicateRoute::class)]
    public function sitemap(): void
    {
        Route::get('/sitemap', [SitemapController::class, 'show'])->name('sitemap');
    }

    public function cacheSeconds(bool $edge): int
    {
        return $edge ? 86400 : 300;
    }
}
