# Shop — fixture app for the detector test suite

A small, coherent Laravel-style app (`Shop\` namespace, `app/` layout). It is **never
run** — it exists only to be *parsed and queried*. Every sin category has at least one
**gold** (senior) implementation and one **messy** (junior) one, so detectors can be
checked against real input/output: "query for pattern X → expect these locations".

These files reference Laravel / Spatie / php-types base classes that are not installed;
that's fine — the engine parses, it does not load them.

## Sin inventory (gold vs messy)

| Concern (skill) | Gold | Messy |
|---|---|---|
| **laravel-idioms** — raw `->input()` | `Http/Requests/*` typed getters (`$this->string/integer/array`) | `OrderController::filter`, `CheckoutController::pay`, `ProductController::search`, `CustomerService::greeting` |
| **laravel-idioms** — container/facade | constructor DI in `OrderController`, `OrderService`, `EmailService`, `OrderHistoryService` | `app()` in `CheckoutController`/`PaymentProcessor`, `resolve()` in `PaymentProcessor`, `Mail::`/`config()` in `NotificationService`, `Log::`/`Cache::` facades |
| **laravel-idioms** — Eloquent scopes | `Order::scopeForCustomer/scopePaid`, used in `OrderRepository`/`OrderHistoryService` | raw `->where('status', …)` in `OrderController::filter`, `ProductController::search` |
| **laravel-idioms** — model mutation | `Order::markPaid()` | `OrderController::markPaid` (`$order->status = 'paid'; save()`), `LegacyOrderImporter::import` |
| **exceptions** — named factories | `*NotFoundException::for*`, `PaymentDeclinedException::forToken` | `new \RuntimeException("…")` in `PaymentProcessor::charge`, `new \LogicException("…")` in `RefundService::reasonFor` |
| **value-objects** / **fix-at-the-source** — god-DTO | honest `OrderData`/`ProductData`/`CustomerData` | all-nullable `RawWebhookPayload` + `WebhookDecoder` re-validating it |
| **value-objects** — primitive obsession | `Sku`, `Email`, `Money` value objects | raw string sku/email/amount threaded in `ProductImporter`/`CustomerService` |
| **spatie-data** — `::from` not `new` | `OrderPresenter::present` (`::from`) | `OrderPresenter::legacyPresent` (`new`), `ProductImporter`, `OrderAssembler` |
| **spatie-data** — no manual hydration / collect | `OrderData` w/ `#[DataCollectionOf]` | `OrderAssembler` (field-by-field + `new OrderLineData` in a loop) |
| **enums-with-behaviour** | `OrderStatus::isTerminal/label`, `ProductCategory::taxRate` | `OrderPresenter::badge` (`match` on `->value` at the call site), `'pending'`/`'paid'` string literals in `OrderController`/`OrderService` |
| **guard-clauses-and-flow** | `RefundService::refund` (top guard), `WebhookDecoder::handle`, `PaymentProcessor::charge` | `RefundService::reasonFor` (inline `?? throw` feeding `strtoupper`) |
| **absence** | resolve-or-throw in `*Repository`/`OrderService::find` | `CustomerService::find` / `LegacyOrderImporter::findCustomer` (`?T` finder every caller de-nulls) |
| **role-vocabulary** | `PaymentGatewayRegistry extends Registry` (get throws) | `NotificationChannels` (hand-rolled keyed store, `get()` returns null) |
| **documentation** | one-line docblocks throughout | `LegacyOrderImporter` (bloated docblock + `// previously…` / `// changed from…` archaeology) |
| **concurrent-state** | `CheckoutSession::for(): Concurrent<self>` | `Cache::get()` in `CustomerService::greeting` |
