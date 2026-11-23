> A route action is the thin step between a request and the domain. One operation has one entry point and one
> implementation.
> Read this before you add a controller action or a route, call one controller from another, write the same
> operation in a command and a controller, or look a route up by name, and when a route-actions finding points here.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**An action reads its request, calls into the domain, and shapes the response. Nothing else, and never twice.**

A controller method wired to a URL does three small things: it binds and validates the request, it hands the work to
a service or an action class, and it returns a response. The work itself lives below it, once.

From that, four rules:

1. **Delegate down, never sideways.** An action calls a service. It does not call another controller's action: that
   is a second entry point to an operation that already has one.
2. **One operation, one action.** Two actions with the same thin body are the same entry point twice.
3. **One action, one route.** A second URL bound to the same `[Controller, method]` is two names for one handler,
   and their middleware and names drift apart. (An invokable controller answering at several fixed URLs is fine.)
4. **One operation, one implementation, whatever the entry.** A console command, an HTTP action and an MCP tool that
   do the same thing each translate their own protocol and call the same application service.

## The sins

### route-delegates-to-controller

A route action that forwards to another controller's action. The operation already has a home, and this is a second
door to it that must be kept in step for ever. Move the shared work into a service both routes call, or point the
route at the real action and delete the wrapper.

```php
// Bad
final class ShopQuickPadController
{
    public function __construct(private readonly QuickPadController $quickPads) {}

    public function moveKey(Shop $shop, QuickPad $quickPad, MoveKeyRequest $request): JsonResponse
    {
        return $this->quickPads->moveKey($request, $quickPad);   // ← a controller calling a controller
    }
}

// Good: both actions call the same service
final class ShopQuickPadController
{
    public function __construct(private readonly MoveQuickPadKey $moveKey) {}

    public function moveKey(Shop $shop, QuickPad $quickPad, MoveKeyRequest $request): JsonResponse
    {
        return new JsonResponse($this->moveKey->handle($quickPad, $request->move()));
    }
}
```

### duplicate-route-action

Two actions in different controllers that both only hand the request to the same operation. That is one entry point
written twice. Keep one action and point both routes at it.

```php
// Bad
final class WorkflowExportController
{
    public function export(Workflow $workflow): Response
    {
        return $this->exporter->export($workflow);
    }
}

final class WorkflowEditorExportController
{
    public function export(Workflow $workflow): Response
    {
        return $this->exporter->export($workflow);   // ← the same action, a second time
    }
}

// Good
Route::get('/workflows/{workflow}/export', [WorkflowExportController::class, 'export'])->name('workflows.export');
```

### duplicate-route

Two registrations of the same verb that bind different URLs to the same `[Controller, method]`. Keep one route. If a
second URL is really needed, make it a redirect.

```php
// Bad
Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
Route::get('/daily-report', [ReportController::class, 'daily']);   // ← a second URL for the same handler

// Good
Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
Route::redirect('/daily-report', '/reports/daily');
```

### boundary-duplicated-operation

The same domain operation written out by hand at two different entry points: a console command and an MCP tool, a
controller and a command. One operation now has two implementations, and a fix reaches only one. Move the shared
steps into one application service, and let each entry translate its own input and call it.

```php
// Bad
final class ArchiveOrdersCommand extends Command
{
    public function handle(): int
    {
        $orders = Order::olderThan($this->option('days'))->get();
        $orders->each->archive();                          // ← the operation, written here
        $this->audit->record('orders.archived', $orders->count());

        return self::SUCCESS;
    }
}

final class ArchiveOrdersTool
{
    public function handle(ArchiveOrdersRequest $request): string
    {
        $orders = Order::olderThan($request->days())->get();
        $orders->each->archive();                          // ← and written again here
        $this->audit->record('orders.archived', $orders->count());

        return "Archived {$orders->count()} orders";
    }
}

// Good
final class ArchiveOrders
{
    public function olderThan(int $days): int { … }
}

final class ArchiveOrdersCommand extends Command
{
    public function handle(ArchiveOrders $archive): int
    {
        $archive->olderThan((int) $this->option('days'));

        return self::SUCCESS;
    }
}
```

### dangling-route-name

A `route('…')` lookup for a name no route registers. Nothing checks the string until the request runs, and then it
is a 500. Point the lookup at the registered name, or register the route the name promises. Renaming a route means
renaming every reference in the same change.

```php
// Bad
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

return redirect()->route('order.show', $order);   // ← no route is named order.show

// Good
return redirect()->route('orders.show', $order);
```
