# Pass the object, not its id — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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
