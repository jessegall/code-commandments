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

// The FIX: render-queue-record is hoisted into `LabelPrinting`, the ONE home for the operation, and
// every face calls it. What is left at this boundary is the only work that is genuinely its own —
// translating the protocol and shaping the answer an agent reads.

public function handleDelegating(string $sku, LabelPrinting $printing): string
{
    return $this->answer($printing->print($sku));
}
```

### dangling-route-name

A `route('x')` lookup naming a route no registration mints — a stringly cross-reference that only fails at runtime, as a 500

```php
----------[ Bad ]----------

// A misspelt name buried in a menu array: every other entry resolves, so the page renders until a
// user clicks THIS one.

public function menu(): array
{
    return [
        ['label' => 'Home', 'href' => route('dashboard')],
        ['label' => 'Overview', 'href' => route('dashbord')],
    ];
}

----------[ Good ]----------

// The FIX: the same menu, with every `route(...)` naming a route the table actually registers —
// `dashbord` was pointed back at a name `routes/web.php` mints (`reports.daily`). The vocabulary is
// closed, so the reference and the registration are renamed in the same breath.

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

// The FIX: `[CatalogueListController, 'list']` is registered ONCE. The second URL survives as a
// REDIRECT, so there is a single handler — and its name, middleware and constraints have no twin
// to drift away from.

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

public function trend(ReportExportRequest $request): string
{
    return $this->trends->plot($request);
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

// The FIX: the wrapper is gone. This action delegates INTO the domain — the same `WorkflowExporter`
// the export controller calls — with the admin translation (`exportForAudit`) named on the service,
// so there is no second HTTP door hanging off another controller.

public function audit(string $id): string
{
    return $this->exporter->exportForAudit($id);
}
```
