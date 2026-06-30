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

Forms of one rule — **lean on the framework's mechanism; keep behaviour where it belongs:**

1. **Request input** → typed accessors, exposed as named getters on the request class.
2. **A Fluent / value bag** → typed accessors, never untyped `->get()`.
3. **Every dependency** → a required constructor parameter, not resolved by hand.
4. **Eloquent queries** → named scopes, not where-clauses repeated at call sites.
5. **Eloquent mutations** → intention-revealing methods on the model, not a bare `update([...])` or
   set-then-`save()` at the call site.

## Rule 1 — request: typed getters, never raw `->input()`

Never read request data with `->input()` / `->get()` / `->query()` — they return `mixed`, so every caller
re-coerces. Read it through the request's **typed accessors** (`->string()`, `->integer()`, `->boolean()`,
`->date()`, `->enum()`, …) and expose each field as a **named getter method on the request class**, so call
sites read a typed value by intent.

```php
// Bad — raw, untyped, re-coerced at every call site
$name  = $request->input('name');              // mixed
$limit = (int) $request->input('limit', 10);   // manual cast

// Good — typed accessors behind named getters on the FormRequest
final class CreateWorkflowRequest extends FormRequest
{
    public function name(): Stringable          { return $this->string('name'); }
    public function limit(): int                { return $this->integer('limit'); }
    public function status(): TurnStatus         { return $this->enum('status', TurnStatus::class); }
}

// call site
$request->name();   // typed, intention-revealing — not $request->input('name')
```

The named accessor is the **one place** the type is settled. Inside it, return the typed value — the
`Stringable` from `->string()`, or `->toString()` once if the field is a plain `string`. The sin is doing
that coercion **at the call site** (`$request->string('id')->toString()` scattered through a handler): that
re-reads raw input by key and is the same mistake as `->input()`. Read the named accessor instead.

**MCP tools too.** A tool's input is a request like any other — don't reach into the raw request inside
`handle()`. Give each tool a **named request class** (extending your MCP request base, the analogue of
`FormRequest`) with `rules()` and named accessors, and read *that*:

```php
// Bad — raw, untyped reads scattered through the tool body
$dispatcher->rename(
    $request->string('id')->toString(),
    $request->string('nodeId')->toString(),
    $request->string('from')->toString(),
);

// Good — one named request class per tool: keys, rules, and types in one place
final class RenameSocketRequest extends ToolRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'id'     => ['required', 'string'],
            'nodeId' => ['required', 'string'],
            'from'   => ['required', 'string'],
        ];
    }

    public function workflowId(): string { return $this->string('id')->toString(); }
    public function nodeId(): string     { return $this->string('nodeId')->toString(); }
    public function fromSocket(): string { return $this->string('from')->toString(); }
}

// in the tool — the body reads intentions, not string keys
$dispatcher->rename($request->workflowId(), $request->nodeId(), $request->fromSocket());
```

Every MCP tool gets its own named request — never `->string('key')->toString()` inline in the tool.

## Rule 2 — Fluent / value bag: typed accessors, never `->get()`

A `Fluent` or a `ValueBag` exposes typed reads. `->get('key')` returns `mixed` and infers nothing — use
the typed accessor so the type flows.

```php
// Bad — untyped; the caller has no idea what comes back
$port = $bag->get('port');                 // mixed

// Good — typed read; the type is inferred
$port = $bag->string('port');              // string
$count = $bag->integer('count');           // int
```

## Rule 3 — dependencies: REQUIRED constructor injection

Every dependency is a **required constructor parameter** (non-nullable, no default). Inside a class you do
**not** call `app()`, `resolve()`, `Container::getInstance()`, a facade, or `new SomeService(...)` — those
hide the dependency, defeat substitution, and make the class untestable without booting the container.

```php
// Bad — dependencies resolved by hand, hidden from the signature
final class WorkflowRunner
{
    public function run(string $id): void
    {
        $repo = app(WorkflowRepository::class);     // hidden dependency
        $clock = Carbon::now();                       // facade reach
    }
}

// Good — declared, injected, substitutable
final class WorkflowRunner
{
    public function __construct(
        private readonly WorkflowRepository $repository,
        private readonly Clock $clock,
    ) {}
}
```

- Tests resolve services through the container (`$this->app->make(...)`), never `new` with hand-wired args.
- Config is injected too: don't read `config('…')` in a class — inject the typed config object and add an
  accessor (a `config()` read is the same untyped-`->get()` smell at the config layer).
- **The only exception is where DI is genuinely impossible** — a queued job's runtime construction, a
  static schema builder. Confine it there, and migrate to injection when the surrounding code is reworked.

## Rule 4 — query through named scopes

A `where(...)` chain that expresses a concept — "active", "for this user", "due" — is a **named scope** on
the model, not a clause re-typed at every call site. The scope names the intent and keeps the column
knowledge in one place.

```php
// Bad — the same conditions re-typed wherever you query
Workflow::query()->where('user_id', $id)->where('status', 'active')->get();

// Good — named scopes; the query reads as intent
Workflow::forUser($id)->active()->get();

// on the model
public function scopeForUser(Builder $query, string $userId): void { $query->where('user_id', $userId); }
public function scopeActive(Builder $query): void { $query->where('status', WorkflowStatus::Active); }
```

## Rule 5 — mutate through intention-revealing model methods

Poking attributes then calling `save()`, or a bare `update([...])`, at a call site is an anemic model: the
*what* (a state transition) is scattered as the *how* (which columns) across the codebase. Put the
transition **on the model** as a method that names it and owns its invariants.

```php
// Bad — the transition is an untyped column-poke at the call site
$workflow->status = 'published';
$workflow->published_at = now();
$workflow->save();
// ...or the same as a bare array update
$workflow->update(['status' => 'published', 'published_at' => now()]);

// Good — a named transition that owns its rules
$workflow->publish();

// on the model
public function publish(): void
{
    $this->status = WorkflowStatus::Published;
    $this->published_at = $this->freshTimestamp();
    $this->save();
}
```

The call site says *what* happens (`->publish()`, `->markFailed($reason)`, `->incrementSequenceNumber()`);
the model owns *how*, and the invariants that travel with it.

## Checklist

```
Laravel idioms
- [ ] No raw ->input()/->get()/->query() on a request — typed accessors behind named getters on the request.
- [ ] No untyped ->get() on a Fluent/ValueBag — a typed accessor (->string/->integer/...) so the type flows.
- [ ] Every dependency is a REQUIRED constructor parameter — no app()/resolve()/facade/new inside the class.
- [ ] No config('…') read in a class — inject the typed config object.
- [ ] Manual resolution only where DI is truly impossible (queued-job runtime, static builder), confined there.
- [ ] No where-clause expressing a concept repeated at call sites — it's a named scope on the model.
- [ ] No bare update([...]) / set-then-save() at a call site — an intention-revealing method on the model.
```

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

## Relationship to the other skills

- [`backend/value-objects`](../value-objects/SKILL.md) — a typed request getter / typed bag read returns the typed value the data should already be; raw `->input()` is the loose-array smell at the HTTP edge.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — read input typed at the boundary so nothing downstream re-coerces a `mixed`.
- [`backend/absence`](../absence/SKILL.md) — a typed accessor for an optional field still answers "can this be missing?" honestly (a nullable getter vs a defaulted one), not a bare `->input($k, $default)`.
