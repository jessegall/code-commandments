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
