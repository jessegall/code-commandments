# Tell, don't ask — behaviour belongs with its data — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### feature-envy

Exiled behaviour / feature envy — a method operating on ONE other owned object's internals that belongs ON that object

```php
----------[ Bad ]----------

public function suspend(Customer $customer, string $reason): void
{
    $customer->suspended = true;
    $customer->suspended_reason = $reason;
    $customer->save();
}

----------[ Good ]----------

// in Shop\Customers\CustomerUpdater
public function suspendByTelling(Customer $customer, string $reason): void
{
    $customer->suspend($reason);
}

// in Shop\Models\Customer
// Where every call site's poking-and-saving went: the transition named once, on the model that
// owns the columns it writes.

public function suspend(string $reason): void
{
    $this->suspended = true;
    $this->suspended_reason = $reason;
    $this->save();
}
```

### keyed-lookup-envy

Indirect feature envy — a method that uses an owned object's IDENTITY as a key to look up a fact about it through a collaborator

```php
----------[ Bad ]----------

public function forItem(CatalogItem $item): array
{
    return $this->registry->has($item->code)
        ? $this->registry->get($item->code)->reservedSkus
        : [];
}

----------[ Good ]----------

// in Shop\Catalog\ReservedSkus
// The item knows its own reserved SKUs — ask it directly instead of using its
// code as a key back into a registry.

public function forItemDirect(CatalogItem $item): array
{
    return $item->reservedSkus();
}

// in Shop\Catalog\CatalogItem
// Where the registry lookup went. The item held the key all along, so it can answer the
// question the key was being used to ask.

public function reservedSkus(): array
{
    return $this->reserved;
}
```

### type-switch

two or more `instanceof` tests on the same subject deciding different branches — asking a value what it IS instead of telling it what to do

```php
----------[ Bad ]----------

public function price(Freightable $freight): int
{
    if ($freight instanceof ExpressFreight) {
        return $freight->weightGrams() * 12 + 500;
    } elseif ($freight instanceof PalletFreight) {
        return $freight->pallets() * 4_000;
    }

    return $freight->weightGrams() * 3;
}

----------[ Good ]----------

// in Shop\Shipping\ConsignmentFee
// The FIX: one method on the shared interface, implemented per freight type. Each kind answers
// for itself, so a new kind needs no edit here at all.

public function priceTold(PricedFreight $freight): int
{
    return $freight->priceCents();
}

// What the caller actually wants to know. Each kind of freight answers it for itself, so pricing a
// new kind adds a class rather than a branch.

interface PricedFreight
{
    public function priceCents(): int;
}

// in Shop\Shipping\ExpressFreight
public function priceCents(): int
{
    return $this->weightGrams() * 12 + 500;
}

// in Shop\Shipping\PalletFreight
public function priceCents(): int
{
    return $this->pallets() * 4_000;
}
```
