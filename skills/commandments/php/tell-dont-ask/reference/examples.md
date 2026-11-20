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

### guard-beside-transition

A caller checks an object's state against an enum case and throws, then tells that object to change: the rule sits outside the transition it guards

```php
----------[ Bad ]----------

public function handBack(RepairTicket $ticket): void
{
    if ($ticket->state !== RepairState::Open) {
        throw RepairNotOpen::forTicket($ticket->number);
    }

    $ticket->close();
}

----------[ Good ]----------

// in Shop\Repairs\RepairDesk
public function handOver(RepairTicket $ticket): void
{
    $ticket->closeOpen();
}

// in Shop\Repairs\RepairTicket
public function closeOpen(): void
{
    if ($this->state !== RepairState::Open) {
        throw RepairNotOpen::forTicket($this->number);
    }

    $this->state = RepairState::Fixed;
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

### middle-man

A class whose every public method only hands its arguments, unchanged, to the same field: it adds nothing between the caller and the object that does the work.

```php
----------[ Bad ]----------

// Asks the carrier where a parcel is. Every method hands its tracking code straight to the carrier client.

final class ParcelTracker
{
    public function __construct(private readonly CarrierClient $client) {}

    public function status(string $tracking): string
    {
        return $this->client->status($tracking);
    }

    public function hoursToDelivery(string $tracking): int
    {
        return $this->client->hoursToDelivery($tracking);
    }
}

----------[ Good ]----------

// The tracking page, asking the carrier client itself rather than a class that only passes the question on.

final class TrackingPage
{
    public function __construct(private readonly CarrierClient $client) {}

    public function headline(string $tracking): string
    {
        return sprintf('%s, %d hours to go', $this->client->status($tracking), $this->client->hoursToDelivery($tracking));
    }
}
```

### train-wreck

`$order->customer()->address()->city()` — a chain crossing three of the project's own types, so a change three objects away reaches this caller

```php
----------[ Bad ]----------

public function heading(DepotRun $run): string
{
    return $run->depot()->address()->town();
}

----------[ Good ]----------

// in Shop\Dispatch\DepotBoard
public function headingTold(DepotRun $run): string
{
    return $run->town();
}

// in Shop\Dispatch\Depot
public function town(): string
{
    return $this->address->town();
}

// in Shop\Dispatch\DepotRun
public function town(): string
{
    return $this->depot->town();
}
```
