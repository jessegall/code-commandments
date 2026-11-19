<?php

namespace Shop\Reporting;

use Illuminate\Http\Client\Factory;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\HardCodedRoutePath;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Warms the daily report after midnight by asking the shop for it once.
 */
final class DailyReportPinger
{
    public function __construct(private readonly Factory $http) {}

    #[Sinful(HardCodedRoutePath::class)]
    public function warm(): bool
    {
        return $this->http->get('/reports/daily')->successful();
    }

    #[Righteous(HardCodedRoutePath::class)]
    public function healthy(): bool
    {
        return $this->http->get('/healthz')->successful() && $this->http->get('/reports/daily/archive')->successful();
    }
}
