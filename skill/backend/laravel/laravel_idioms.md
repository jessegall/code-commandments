> Laravel already hands you typed input, injected dependencies, named routes, query scopes and a model to put
> behaviour on. Use those, and do not rebuild them by hand at each call site.
> Read this before you read request input, call `config()`, `app()` or a facade inside a class, write a query or a
> URL by hand, or change a model from outside it, and when a laravel-idioms finding points here.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Take the type, the dependency and the name the framework is holding for you.**

Every sin here throws one of those away:

- **Input.** Read request input through a typed accessor, behind a named getter on the request class. That is the one
  place the type is settled; every call site then reads a typed value by its meaning.
- **Dependencies and settings.** A class states what it needs in its constructor: a service behind its interface, a
  typed settings object. Not `app()`, not a facade, not `config('…')` inside a method.
- **Routes.** A path is declared once, where the route is registered. Everywhere else builds it from the route's name.
- **Queries.** A query several callers run is one named scope or finder on the model. A query that must read past a
  global scope names the one scope it leaves.
- **Models.** A model changes through a method that says what happened (`$order->markPaid()`), never through an
  anonymous array of columns or a property poke followed by `save()`.
- **Wiring.** A config key and a listener exist because something reads or fires them. When the last user goes, they go.

```php
// Bad
public function store(Request $request): RedirectResponse
{
    $order = Order::query()->where('id', $request->input('order_id'))->first();   // ← untyped input, query by hand
    $order->status = 'paid';                                                       // ← the caller edits the model's columns
    $order->save();

    Mail::to($order->customer)->send(new OrderPaid($order));                       // ← a hidden dependency

    return redirect('/orders/'.$order->id);                                        // ← the path, written again
}

// Good
public function store(PayOrderRequest $request, Mailer $mailer): RedirectResponse
{
    $order = $request->order();
    $order->markPaid();

    $mailer->to($order->customer)->send(new OrderPaid($order));

    return redirect()->route('orders.show', $order);
}
```

## The sins

### raw-request-input

Raw `->input()`, `->get()`, `->query()` or `->post()` on a request. Each returns `mixed`, so every call site decides
the type again. Read through a typed accessor, behind a named getter on a `FormRequest`.

```php
// Bad
$productId = $request->input('product_id');   // ← mixed: a string, an array, or null

// Good
final class AddToCartRequest extends FormRequest
{
    public function productId(): int
    {
        return $this->integer('product_id');
    }
}

$productId = $request->productId();
```

### request-accessor-recast

Casting the `Stringable` that a typed accessor returns with `(string)`. The accessor already has a method for it.

```php
// Bad
$nodeId = (string) $request->string('nodeId');   // ← a cast where toString() says the same

// Good
$nodeId = $request->string('nodeId')->toString();
```

### config-read

`config('…')` read inside a class. The class depends on a setting that its constructor does not show, by a string
key that nothing checks. Inject a typed settings object.

```php
// Bad
final class InvoiceNumberer
{
    public function next(): string
    {
        return config('billing.prefix').$this->sequence->next();   // ← a hidden dependency on a string key
    }
}

// Good
final class InvoiceNumberer
{
    public function __construct(
        private readonly BillingSettings $settings,
        private readonly Sequence $sequence,
    ) {}

    public function next(): string
    {
        return $this->settings->prefix.$this->sequence->next();
    }
}
```

### duplicated-config-default

A config key whose default is stated twice: once in the config file, and again as the reader's inline fallback. The
two will drift apart. The config file owns the default.

```php
// Bad
// config/relay.php
'heartbeat_seconds' => env('RELAY_HEARTBEAT', 30),

// elsewhere
$seconds = config('relay.heartbeat_seconds', 15);   // ← a second default, and a different one

// Good
$seconds = config('relay.heartbeat_seconds');
```

### dead-config-key

A config key that nothing reads. It was left behind by a deleted feature, and new code may adopt it by mistake.
Delete the key and its env variable, or restore the reader the feature lost.

```php
// Bad
// config/shop.php
return [
    'currency' => env('SHOP_CURRENCY', 'EUR'),
    'legacy_tax_mode' => env('SHOP_LEGACY_TAX', false),   // ← nothing reads shop.legacy_tax_mode
];

// Good
return [
    'currency' => env('SHOP_CURRENCY', 'EUR'),
];
```

### facade-call

A facade call (`Cache::`, `Log::`, `Mail::` …) inside a class. It is a dependency the constructor does not show.
Inject the service behind its contract.

```php
// Bad
final class RateBook
{
    public function rateFor(string $currency): float
    {
        return Cache::remember("rate.{$currency}", 3600, fn () => $this->fetch($currency));   // ← hidden dependency
    }
}

// Good
final class RateBook
{
    public function __construct(private readonly Repository $cache) {}

    public function rateFor(string $currency): float
    {
        return $this->cache->remember("rate.{$currency}", 3600, fn () => $this->fetch($currency));
    }
}
```

### hard-coded-route-path

A path written out by hand where a named route already declares it. It keeps working until the route moves, and
then every copy breaks without an error. Build the URL from the route's name.

```php
// Bad
return redirect("/sharing/shares/{$share->id}");   // ← the path of shares.show, written again

// Good
return redirect()->route('shares.show', $share);
```

### repeated-query-chain

One Eloquent query chain written the same way at three or more places outside its model. The knowledge of the
columns is copied to every caller. Give the model a named scope or finder.

```php
// Bad
CommandRequest::query()->where('command_id', $id)->whereNull('released_at')->lockForUpdate()->first();
// ↑ the same chain stands in two other classes

// Good
final class CommandRequest extends Model
{
    public static function lockedFor(string $id): ?self
    {
        return self::query()->where('command_id', $id)->whereNull('released_at')->lockForUpdate()->first();
    }
}

CommandRequest::lockedFor($id);
```

### bare-scope-escape

A query that drops every global scope with a bare `withoutGlobalScopes()`. It also drops the scopes someone adds
next year for another reason. Name the one scope the query leaves, or pass whose rows it reads as an argument.

```php
// Bad
$subscriptions->withoutGlobalScopes()->whereBetween('ended_at', [$from, $until])->get();   // ← drops all of them

// Good
$subscriptions->withoutGlobalScope(OwnerScope::class)->whereBetween('ended_at', [$from, $until])->get();
```

### mass-update-at-call-site

A bare `$model->update([...])` with an array of columns, written at a call site. The caller knows the model's
columns, and nothing says what the change means. Give the model a method named for what happens.

```php
// Bad
$order->update(['status' => 'paid', 'paid_at' => now()]);   // ← the caller writes the model's columns

// Good
$order->markPaid();

final class Order extends Model
{
    public function markPaid(): void
    {
        $this->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
    }
}
```

### model-mutation-at-call-site

A property of a model set from outside, followed by `save()`. It is the same sin in a second form: the change has
no name and lives with the caller.

```php
// Bad
$customer->suspended_at = now();          // ← the caller sets the columns
$customer->suspension_reason = $reason;
$customer->save();

// Good
$customer->suspend($reason);

final class Customer extends Model
{
    public function suspend(string $reason): void
    {
        $this->suspended_at = now();
        $this->suspension_reason = $reason;
        $this->save();
    }
}
```

### dead-event-wiring

A listener wired to an event that no code can fire. It reads as live wiring and it never runs. A listener exists to
answer a dispatch: delete the registration (and the listener, if nothing else uses it), or restore the dispatch.

```php
// Bad
protected $listen = [
    ShelfRestocked::class => [NotifyShelfStaff::class],   // ← nothing dispatches ShelfRestocked
];

// Good
protected $listen = [];
```
