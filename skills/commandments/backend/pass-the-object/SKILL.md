---
name: commandments-backend-pass-the-object
description: "Demand the resolved type you need, not an id plus its container. When a method takes a container object AND a key into it, then resolves the key against the container (`request(Workflow $workflow, string $nodeId)` doing `$workflow->graph->nodeById($nodeId)`), the lookup is misplaced — the caller passed both, so the caller already holds everything the lookup needs. Resolve at the caller and hand over the resolved OBJECT, and let the caller own the \"not found\" failure. Read this when a method signature pairs a domain object with a string/int id it then looks up inside."
---

# Pass the object, not its id

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A method that takes `(Workflow $workflow, string $nodeId)` and starts with
> `$workflow->graph->nodeById($nodeId)` is asking for the wrong things. It needs the
> **node**. The caller named the id and holds the workflow — so the caller should
> resolve, and pass the resolved object.

## The principle

When a method's first act is to **resolve one parameter against another** — key a
scalar id into a container object to get the thing it actually works on — the
signature is lying about what the method needs, and the resolution is in the wrong
place. Push it up: the **caller** resolves and passes the resolved object; the method
**demands the type it uses**.

Why it's the caller's job:

- **The caller already holds everything.** It passed both the container and the id,
  so it can call the lookup itself — nothing is gained by deferring it inward.
- **The "not found" failure belongs to whoever named the id.** Burying
  `throw …NotFound` inside the service spreads that error-handling into every method
  that happens to need the object.
- **It's primitive obsession.** `string $nodeId` where a `WorkflowNode` is meant —
  the type doesn't say what the value is for.
- **It couples the method to the container's lookup API** (`$workflow->graph->nodeById`)
  for no reason — the method only ever wanted the node.

The caller resolves once, where the id was born, and every downstream method is
honest about its inputs.

### What is NOT this sin

- **A Registry / Repository keying into its OWN store** — `for($type)` returning
  `$this->providers[$type->value] ?? throw …`. Looking a value up by key against
  `$this` is the object's whole job; the container is not something the caller holds.
- **A boundary entry point** — a controller action or console command resolving a
  **route/argument id** (`__invoke(Request $r, Workflow $w, string $node)`). The id
  arrives as a string from the wire; the boundary *is* where it gets resolved, and
  there's no caller upstream to hand an object.
- **Reflection / generic collection access** — `$reflection->getProperty($name)`,
  `$collection->get($key)`. The "container" is a language facility or data structure,
  not a domain object the caller resolved from.
- **A genuine lookup API whose contract is "by id"** — a method that *exists* to
  resolve (`findById`, `descriptorFor`) and hands the result straight back. The smell
  is only when a method resolves an id **and then orchestrates** on the result while
  pretending it needed the container.

### The tell

A method signature pairs a domain object with a `string`/`int` id, and the body's
first move is `$thatObject->…->somethingById($thatId)`. Ask: *did the caller already
have what it needs to resolve this?* If yes, move the lookup to the caller and change
the parameter to the resolved type.

## Rules

- Take the SUBJECT and ask it — never a bool every caller derives from that same object.
  _swap the flags for the object the callers already hold: `CornerInset::for($editor)`_
- Declare the parameter in the currency callers actually hold, and convert inside — one rule about the conversion, in one place.
  _Move the wrapper into the callee and widen the parameter to the type being wrapped; every call site then passes the value it means, and a site that forgets the conversion stops compiling._
- Pass the subject, not projections of it — a callee reaching the same value twice should take it once and derive the rest.
  _Give the parameter the subject's type and move the derivations inside the callee; the call site then says what it means instead of spelling out the pieces._
- Demand the resolved object you need; don't take a container + key and unpack the target yourself — the caller resolves once and passes it.
  _Take the resolved object as the param; resolve once in the caller._

## Bad → good

### computed-boolean-argument

a bool-only chooser whose callers all compute the flag off the same object (take the object and ask it)

```php
----------[ Bad ]----------

// Told, not asked: the editor is what this is ABOUT, yet the signature names only the answer.
// Every caller has to remember which of the editor's modes count — and the one that forgets
// the panel leaves the corners tucked in mid-mode.

public static function of(bool $tucked): string
{
    return $tucked ? 'tight' : 'wide';
}

----------[ Good ]----------

// The FIX: take the SUBJECT the callers already hold. `CornerInset::for($editor)` asks the editor
// itself, so the rule about which modes tuck the corners in lives here once — no call site holds a
// half-remembered copy of it, and none can forget the panel.

public static function for(KioskEditor $editor): string
{
    return $editor->inZenMode() || $editor->hasPanelOpen() ? 'tight' : 'wide';
}
```

### converted-argument

A parameter declared in the wrong currency — call site after call site wraps the same argument in the same conversion (`Raises::of(ClassAlias::of($interaction), …)`) because the callee asks for the converted form instead of the value

```php
----------[ Bad ]----------

// in Shop\Wire\HotkeyBinding
public function bind(string $node): WireMessage
{
    return WireMessage::raise(SignalAlias::of(HotkeyPressed::class), $node);
}

// in Shop\Wire\PointerBinding
public function bind(string $node): WireMessage
{
    $this->bound[] = $node;

    return WireMessage::raise(SignalAlias::of(PointerReleased::class), $node);
}

// in Shop\Shelving\ShelfImporter
public function import(string $heading, int $bay): void
{
    $this->index->reserve(SlugText::of($heading), $bay);
}

// in Shop\Shelving\ShelfPlanner
public function plan(array $aisles): void
{
    foreach ($aisles as $bay => $name) {
        $this->index->reserve(SlugText::of($name), $bay);
    }
}

----------[ Good ]----------

// The FIX: the parameter is declared in the currency the caller holds, and the conversion lives on
// the far side of the call.

public function bindDirect(string $node): WireMessage
{
    return WireMessage::raiseFor(HotkeyPressed::class, $node);
}
```

### derived-argument

Handing one subject to a call TWICE over — whole and again flattened (`persist($request, $request->shopId())`), or flattened several ways (`new AgentTurn($r->output(), $r->failed(), $r->errorOutput())`) — when the callee could derive every piece from the subject itself

```php
----------[ Bad ]----------

public function dispatch(Waybill $waybill): string
{
    return $this->courier->book(
        $waybill->trackingCode(),
        $waybill->weightGrams(),
        $waybill->isHeavy(),
    );
}

----------[ Good ]----------

// The FIX: hand over the waybill and let the courier read what it needs off it.

public function dispatchWhole(Waybill $waybill): string
{
    return $this->courier->bookWaybill($waybill);
}
```

### param-resolved-from-param

Unpacking the target out of a container param — a method takes `(Workflow $workflow, string $nodeId)` and resolves `$workflow->graph->nodeById($nodeId)`, then works on the target while the container is only packaging

```php
----------[ Bad ]----------

public function priceFor(ProductCatalogue $catalogue, string $sku): int
{
    $variant = $catalogue->variantBySku($sku);

    return $variant->basePriceCents() + $this->markupCents;
}

----------[ Good ]----------

// Demands the resolved variant — the caller resolves it once by sku and owns
// the "not found" failure, so this only prices what it is handed.

public function priceForVariant(Variant $variant): int
{
    return $variant->basePriceCents() + $this->markupCents;
}
```

## When it fires

- a bool-only chooser whose callers all compute the flag off the same object (take the object and ask it) — `ComputedBooleanArgumentDetector`
- A parameter declared in the wrong currency — call site after call site wraps the same argument in the same conversion (`Raises::of(ClassAlias::of($interaction), …)`) because the callee asks for the converted form instead of the value — `ConvertedArgumentDetector`
- Handing one subject to a call TWICE over — whole and again flattened (`persist($request, $request->shopId())`), or flattened several ways (`new AgentTurn($r->output(), $r->failed(), $r->errorOutput())`) — when the callee could derive every piece from the subject itself — `DerivedArgumentDetector`
- Unpacking the target out of a container param — a method takes `(Workflow $workflow, string $nodeId)` and resolves `$workflow->graph->nodeById($nodeId)`, then works on the target while the container is only packaging — `ParamResolvedFromParamDetector`

## Checklist

- [ ] Take the SUBJECT and ask it — never a bool every caller derives from that same object.
- [ ] Declare the parameter in the currency callers actually hold, and convert inside — one rule about the conversion, in one place.
- [ ] Pass the subject, not projections of it — a callee reaching the same value twice should take it once and derive the rest.
- [ ] Demand the resolved object you need; don't take a container + key and unpack the target yourself — the caller resolves once and passes it.

## Related skills

- [`backend/value-objects`](../value-objects/SKILL.md) — the object you pass IS the resolved type — give data a type, don't thread an id + its container.
- [`backend/tell-dont-ask`](../tell-dont-ask/SKILL.md) — unpacking a container param to work its target is feature envy on the container.
