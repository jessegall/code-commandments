# Pass the object, not its id — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### computed-boolean-argument

A parameter that's just true/false, computed by every caller from the same object it could be given instead.

```php
----------[ Bad ]----------

public static function of(bool $tucked): string
{
    return $tucked ? 'tight' : 'wide';
}

----------[ Good ]----------

// in Shop\Kiosk\CornerInset
public static function for(KioskEditor $editor): string
{
    return $editor->inZenMode() || $editor->hasPanelOpen() ? 'tight' : 'wide';
}

// in Shop\Kiosk\KioskEditor
public function inZenMode(): bool
{
    return $this->zen;
}
```

### derived-argument

Passing the same object twice — once whole and once broken into a piece (`persist($request, $request->shopId())`), or broken into several pieces at once (`new AgentTurn($r->output(), $r->failed(), $r->errorOutput())`) — when the callee could derive each piece itself from the one object.

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

// in Shop\Dispatch\ParcelDispatch
public function dispatchWhole(Waybill $waybill): string
{
    return $this->courier->bookWaybill($waybill);
}

// in Shop\Dispatch\CourierBooking
public function bookWaybill(Waybill $waybill): string
{
    $heavy = $waybill->isHeavy() ? ':heavy' : '';

    return $waybill->trackingCode() . ':' . $waybill->weightGrams() . $heavy;
}
```

### param-resolved-from-param

Unpacking the target out of a container parameter — a method takes `(Workflow $workflow, string $nodeId)` and resolves `$workflow->graph->nodeById($nodeId)` itself, when it could just receive the node directly.

```php
----------[ Bad ]----------

public function priceFor(ProductCatalogue $catalogue, string $sku): int
{
    $variant = $catalogue->variantBySku($sku);

    return $variant->basePriceCents() + $this->markupCents;
}

----------[ Good ]----------

// in Shop\Catalog\VariantPricer
public function priceForVariant(Variant $variant): int
{
    return $variant->basePriceCents() + $this->markupCents;
}

// in Shop\Catalog\CartPricing
public function lineTotal(string $sku): int
{
    return $this->pricer->priceForVariant($this->catalogue->variantBySku($sku));
}
```
