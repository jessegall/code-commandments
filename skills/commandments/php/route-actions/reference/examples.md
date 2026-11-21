# Route Actions — one operation, one entry point — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### boundary-duplicated-operation

The same domain operation hand-rolled at two DIFFERENT entry boundaries (a console command and an MCP tool, a controller and a command) — one operation with two implementations that drift

```php
----------[ Bad ]----------

public function handle(string $sku, LabelRenderer $renderer, LabelQueue $queue, PrintLog $log): string
{
    $job = $queue->push($renderer->render($sku));

    $log->record($job);

    return $this->answer($job);
}

----------[ Good ]----------

// in Shop\Mcp\PrintLabelTool
public function handleDelegating(string $sku, LabelPrinting $printing): string
{
    return $this->answer($printing->print($sku));
}

final class LabelPrinting
{
    public function __construct(
        private readonly LabelRenderer $renderer,
        private readonly LabelQueue $queue,
        private readonly PrintLog $log,
    ) {}

    public function print(string $sku): string
    {
        $jobId = $this->queue->push($this->renderer->render($sku));

        $this->log->record($jobId);

        return $jobId;
    }
}
```

### dangling-route-name

A `route('x')` lookup naming a route no registration mints — a stringly cross-reference that only fails at runtime, as a 500

```php
----------[ Bad ]----------

public function menu(): array
{
    return [
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Overview', 'href' => route('dashbord')],
    ];
}

----------[ Good ]----------

public function registeredMenu(): array
{
    return [
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Overview', 'href' => route('reports.daily')],
    ];
}
```

### duplicate-route

Two route registrations of the same verb bind different URLs to the SAME `[Controller, method]` — two names for one handler (invokable single-action controllers, commonly aliased to several canonical URLs, are exempt)

```php
----------[ Bad ]----------

public function feed(): void
{
    Route::get('/feed', [FeedListController::class, 'list'])->name('feed');
    Route::get('/rss', [FeedListController::class, 'list'])->name('feed.rss');
}

----------[ Good ]----------

public function catalogue(): void
{
    Route::get('/catalogue', [CatalogueListController::class, 'list'])->name('catalogue');
    Route::redirect('/products.rss', '/catalogue');
}
```

### duplicate-route-action

Two route actions in different controllers thinly delegate to the SAME operation (`return $this->exporter->export(...)`) — the same entry point twice

```php
----------[ Bad ]----------

public function build(ReportExportRequest $request): string
{
    return $this->builder->build($request);
}

----------[ Good ]----------

// in Shop\Http\Controllers\DuplicateActions\AnalyticsTrendController
public function trend(ReportExportRequest $request): string
{
    return $this->trends->plot($request);
}

final class TrendBuilder
{
    public function plot(ReportExportRequest $request): string
    {
        return 'trend';
    }
}
```

### route-delegates-to-controller

A route action forwards to ANOTHER controller's action (`return $this->otherController->action(...)`) — a redundant entry point onto an operation that already has one

```php
----------[ Bad ]----------

public function run(string $id): string
{
    return $this->export->run($id);
}

----------[ Good ]----------

// in Shop\Http\Controllers\Delegated\AdminExportController
public function audit(string $id): string
{
    return $this->exporter->exportForAudit($id);
}

// in Shop\Http\Controllers\Delegated\WorkflowExporter
public function exportForAudit(string $id): string
{
    return 'audit-' . $id;
}
```
