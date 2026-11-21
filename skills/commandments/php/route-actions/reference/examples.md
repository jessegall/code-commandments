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
// The FIX: render-queue-record is hoisted into `LabelPrinting`, the ONE home for the operation, and
// every face calls it. What is left at this boundary is the only work that is genuinely its own —
// translating the protocol and shaping the answer an agent reads.

public function handleDelegating(string $sku, LabelPrinting $printing): string
{
    return $this->answer($printing->print($sku));
}

// The one home for "print a label" — the shared service every face should call.

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
