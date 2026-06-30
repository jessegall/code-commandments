---
name: laravel-idioms
description: Use the framework's typed/injected mechanisms and keep behaviour on the model — read request input through TYPED accessors behind named getters (never raw `->input()`), read a Fluent/ValueBag through typed accessors (never untyped `->get()`), inject every dependency through the constructor (never `app()`/facade/`new`), query through named Eloquent scopes (not repeated where-clauses), and mutate through intention-revealing model methods (never a bare `update([...])` or `$model->x = y; save()` at a call site). Read this BEFORE you call `->input()`/`->get()`, reach for a dependency, write a query, or update a model.
---

# Laravel idioms — typed access, injected deps, behaviour on the model

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model
> to hang behaviour on. Reach for those. Raw `->input()`, untyped `->get()`, `app()`-in-a-method, a
> repeated `where()` chain, and a column-poke-then-`save()` are all the same mistake: throwing away a
> type, a wire, or a name the framework was holding for you.

## The principle

The framework already hands you typed input, typed bags, wired-up dependencies, query scopes, and a model to
hang behaviour on. Reach for those. Raw `->input()`, an untyped `->get()`, `app()`-in-a-method, a `where()`
chain repeated at call sites, and a column-poke-then-`save()` are all the same mistake: throwing away a
type, a wire, or a name the framework was holding for you.

Read request input through the request's **typed accessors**, exposed as **named getter methods on the
request class** — the one place the type is settled, so every call site reads a typed value by intent
instead of re-coercing `mixed`. An MCP tool's input is a request like any other: give each tool its own
named request class (the analogue of a `FormRequest`), with its keys, rules and types in one place, and
read *that* — never the raw request inside `handle()`.

Hold every dependency as a required constructor parameter, never resolved by hand from the container.
Express a query concept that recurs across call sites as a **named Eloquent scope**, so the column knowledge
lives in one place instead of being re-typed wherever you query. And mutate a model through
**intention-revealing methods** (`$order->markPaid()`) that say what changed and why — not a bare
`update([...])` or a set-property-then-`save()` smeared across the call site.

## Rules

- Inject a typed config object; never read `config('…')` inside a class.
  _Inject a typed config value object._
- Declare dependencies in the constructor; never reach into the container with `app()`/`resolve()` from a resolved class.
  _Declare the dependency as a constructor parameter._
- Inject the dependency; never call a Laravel facade (`Cache::`, `Log::`, `Mail::`) inside a class.
  _Constructor-inject the dependency behind its interface._
- Mutate a model through an intention method; never `$model->update([...])` an anonymous array of columns at a call site.
  _An intention method on the model (`$order->markPaid()`)._
- Mutate a model through an intention method; don't set-property-then-`save()` at a call site.
  _An intention method on the model (`$order->suspend($reason)`)._
- Read request input through a typed accessor (`$request->string('x')`); never raw `->input()`/`->get()`/`->query()`.
  _A named getter on a `FormRequest` subclass (`$request->productId()`)._
- Expose a named getter on a typed request class; don't re-coerce a typed accessor (`$request->string('id')->toString()`) at a call site.
  _A named getter on a typed request class returning the coerced value._

## Bad → good

```php
// Bad
public function search(array $filters): array
{
    $perPage = config('shop.catalog.per_page');

    // used to filter in PHP, moved to the query builder in v3
    $term = $filters['q'];
    $sort = $filters['sort'];

    return $this->run($term, $sort, $perPage);
}

// Good
public function searchTop(string $term, string $sort): array
{
    return $this->run($term, $sort, $this->settings->perPage);
}
```

```php
// Bad
public function pay(Request $request): array
{
    $processor = app(PaymentProcessor::class);
    $token = $request->input('token');

    $result = $processor->charge($token, (int) $request->input('amount'));

    return ['ok' => $result];
}

// Good
public function payClean(PaymentProcessor $processor, string $token, int $amount): array
{
    return ['ok' => $processor->charge($token, $amount)];
}
```

```php
// Bad
public function notify(string $email, string $type): void
{
    $template = config('shop.templates.' . $type);

    Mail::raw($template, function ($message) use ($email) {
        $message->to($email);
    });
}

// Good
public function notifyClean(string $email, string $template): void
{
    $this->mailer->raw($template, function ($message) use ($email) {
        $message->to($email);
    });
}
```

```php
// Bad
public function verify(Customer $customer): void
{
    $customer->update([
        'verified' => true,
        'verified_at' => $this->now,
    ]);
}

// Good
public function verifyNamed(Customer $customer): void
{
    $customer->markVerified($this->now);
}
```

```php
// Bad
public function suspend(Customer $customer, string $reason): void
{
    $customer->suspended = true;
    $customer->suspended_reason = $reason;
    $customer->save();
}

// Good
public function suspendNamed(Customer $customer, string $reason): void
{
    $customer->suspend($reason);
}
```

```php
// Bad
public function handle(Request $request): string
{
    $id = $request->get('id');
    $name = $request->get('name');

    return $id . ':' . $name;
}

// Good
public function handleTyped(Request $request): string
{
    $id = $request->string('id');
    $name = $request->string('name');

    return $id . ':' . $name;
}
```

```php
// Bad
public function handle(Request $request): string
{
    $nodeId = (string) $request->string('nodeId');
    $direction = (string) $request->string('direction');

    return $nodeId.'->'.$direction;
}

// Good
public function handleNamed(MoveNodeRequest $request): string
{
    $nodeId = $request->nodeId();
    $direction = $request->direction();

    return $nodeId.'->'.$direction;
}
```

## When it fires

- `config('…')` read inside a class — `ConfigReadDetector`
- `app()`/`resolve()` reach inside a container-resolved class — `ContainerReachDetector`
- Laravel facade call (`Cache::`, `Log::`, `Mail::` …) — `FacadeCallDetector`
- Bare `$model->update([...])` mass-array update at a call site — `MassUpdateAtCallSiteDetector`
- Set-property-then-`save()` at a call site (should be an intention method) — `ModelMutationAtCallSiteDetector`
- Raw `->input()/->get()/->query()` on a Request — `RawRequestInputDetector`
- Re-coercing a typed request accessor at a call site — `$request->string('id')->toString()` instead of a named getter on a request class — `RequestAccessorRecastDetector`

## Checklist

- [ ] Inject a typed config object; never read `config('…')` inside a class.
- [ ] Declare dependencies in the constructor; never reach into the container with `app()`/`resolve()` from a resolved class.
- [ ] Inject the dependency; never call a Laravel facade (`Cache::`, `Log::`, `Mail::`) inside a class.
- [ ] Mutate a model through an intention method; never `$model->update([...])` an anonymous array of columns at a call site.
- [ ] Mutate a model through an intention method; don't set-property-then-`save()` at a call site.
- [ ] Read request input through a typed accessor (`$request->string('x')`); never raw `->input()`/`->get()`/`->query()`.
- [ ] Expose a named getter on a typed request class; don't re-coerce a typed accessor (`$request->string('id')->toString()`) at a call site.

## Related skills

- [`backend/value-objects`](../value-objects/SKILL.md) — a typed request getter / typed bag read returns the typed value the data should already be; raw `->input()` is the loose-array smell at the HTTP edge.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — read input typed at the boundary so nothing downstream re-coerces a `mixed`.
- [`backend/absence`](../absence/SKILL.md) — a typed accessor for an optional field still answers "can this be missing?" honestly (a nullable getter vs a defaulted one), not a bare `->input($k, $default)`.
