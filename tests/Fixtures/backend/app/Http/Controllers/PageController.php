<?php

namespace Shop\Http\Controllers;

use Illuminate\Routing\Controller;
use Shop\Http\Pages\AccountPage;
use Shop\Http\Pages\AiWorkflowPage;
use Shop\Http\Pages\CatalogPage;
use Shop\Http\Pages\CheckoutSummaryPage;
use Shop\Http\Pages\DashboardPage;
use Shop\Http\Pages\MetricsPage;
use Shop\Http\Pages\OverviewPage;
use Shop\Http\Pages\PrintQueuePage;
use Shop\Http\Pages\ReportPage;
use Shop\Http\Pages\SystemStatusPage;
use Shop\Http\Pages\TimelinePage;
use Shop\Http\Pages\WarehousePage;

/**
 * Returns each page object — this is what makes them response-bound (a Spatie `Data` is `Responsable`,
 * so returning it IS the response), and therefore genuine page objects rather than internal aggregates.
 */
final class PageController extends Controller
{
    public function dashboard(): DashboardPage
    {
        return DashboardPage::from([]);
    }

    public function checkout(): CheckoutSummaryPage
    {
        return CheckoutSummaryPage::from([]);
    }

    public function catalog(): CatalogPage
    {
        return CatalogPage::for('books');
    }

    public function account(): AccountPage
    {
        return AccountPage::from([]);
    }

    public function report(): ReportPage
    {
        return ReportPage::from([]);
    }

    public function aiWorkflow(): AiWorkflowPage
    {
        return AiWorkflowPage::from([]);
    }

    public function systemStatus(): SystemStatusPage
    {
        return SystemStatusPage::from([]);
    }

    public function printQueue(): PrintQueuePage
    {
        return PrintQueuePage::for('agent-1');
    }

    public function overview(): OverviewPage
    {
        return OverviewPage::from([]);
    }

    public function timeline(): TimelinePage
    {
        return TimelinePage::for('order-1');
    }

    public function metrics(): MetricsPage
    {
        return MetricsPage::from([]);
    }

    public function warehouse(): WarehousePage
    {
        return WarehousePage::from([]);
    }
}
