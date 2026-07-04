<?php

namespace Shop\Routing;

/**
 * Minimal action targets the route providers bind to — enough for the `[Controller::class, 'method']`
 * references to resolve.
 */
final class ReportViewController
{
    public function show(): string
    {
        return 'report';
    }
}

final class WebhookIngestController
{
    public function handle(): string
    {
        return 'ok';
    }
}

final class FeedListController
{
    public function list(): string
    {
        return 'feed';
    }
}

final class SitemapController
{
    public function show(): string
    {
        return 'sitemap';
    }
}
