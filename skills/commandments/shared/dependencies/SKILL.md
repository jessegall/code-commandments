---
name: commandments-shared-dependencies
description: "Read before importing anything between modules or layers."
---

# Dependencies: keep imports going in one direction

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> An import states which module a part of the project depends on.

## The principle

When two modules need the same contract, give that contract a home both may import.

## Rules

- [ ] Take a contract for the cross-module dependency.
      _Take a contract for the cross-module dependency._
- [ ] Put the guard inside the transition that owns the state.
      _Put the guard inside the transition that owns the state._
- [ ] Use the project's registry abstraction for keyed storage.
      _Use the project's registry abstraction for keyed storage._
- [ ] Use the accessible contract for identical signatures. Extend it to add the remaining signatures.
      _Check that the contracts promise the same behaviour before reusing the existing one._
- [ ] Use the implementation directly unless the interface is a boundary.
      _Use the implementation directly unless the interface is a boundary._
- [ ] Give callers the type that owns the operations.
      _Give callers the type that owns the operations._
- [ ] Give the dependency one direction.
      _Move the shared contract to a module both can use._
- [ ] Keep references within the layers declared for their source.
      _Move the call or its contract to a layer this source may use._
- [ ] Remove the obligation or choose a type that supports it.
      _Remove the obligation or choose a type that supports it._
- [ ] Ask the nearest owner for the behavior the caller needs.
      _Ask the nearest owner for the behavior the caller needs._
- [ ] Read what the declared type guarantees. When only some implementers carry the member, give them a contract that declares it and type the receiver by that contract.
      _Declare the member on the carrying types' contract and use that contract at the access._
- [ ] Remove the unused collaborator or use it where it is needed.
      _Remove the unused collaborator or use it where it is needed._
- [ ] Remove the unused private member.
      _Remove the unused private member._
- [ ] Use a constant or a named operation.
      _Use a constant or a named operation._

## Worked example

### concrete-cross-module-dependency — in PHP

A constructor takes another module's concrete service

```php
----------[ Bad ]----------

public function __construct(private readonly ProductRepository $products) {}

----------[ Good ]----------

// in Shop\Harvest\YieldForecast
public function __construct(
    private CropCalendar $crops,
    private WateringCalendar $water,
) {}

// in Shop\Irrigation\WaterSchedule
public function litresDueOn(string $bed, int $day): int
{
    return (int) $this->database->table('watering')->where('bed', $bed)->where('day', $day)->sum('litres');
}

interface WateringCalendar
{
    public function litresDueOn(string $bed, int $day): int;
}
```

### concrete-cross-module-dependency — in TypeScript

A constructor takes another module's concrete service

```ts
----------[ Bad ]----------

constructor(
    private readonly service: AccountReferenceTreeService,
) {}

----------[ Good ]----------

constructor(
    private readonly formatter: ReceiptFormatter,
    private readonly reference: string,
) {}
```

### concrete-cross-module-dependency — in Python

A constructor takes another module's concrete service

```py
----------[ Bad ]----------

def __init__(
    self,
    tracking: ParcelTreeService,
    event: str,
    subscribers: list[str],
):
    self.tracking = tracking
    self.pending = [f"{event}/{name}" for name in subscribers if name]

----------[ Good ]----------

def __init__(
    self,
    receipt: ReceiptFormatter,
    reference: str,
):
    self.receipt = receipt
    self.reference = reference
```

### concrete-cross-module-dependency — in C#

A constructor takes another module's concrete service

```cs
----------[ Bad ]----------

public sealed class ParcelSettingsTreeOwner
{
    private readonly ParcelSettingsTreeImportedService reader;

    public ParcelSettingsTreeOwner(
        ParcelSettingsTreeImportedService reader)
    {
        this.reader = reader;
    }

    public string Record() { return this.reader.Read() + "!"; }
}

----------[ Good ]----------

public sealed class ReceiptJob
{
    private readonly IReceiptFormatter formatter;
    private readonly string reference;

    public ReceiptJob(
        IReceiptFormatter formatter,
        string reference)
    {
        this.formatter = formatter;
        this.reference = reference;
    }

    public string Caption()
    {
        return "Customer copy: " + this.FormatCurrent();
    }

    private string FormatCurrent()
    {
        return this.formatter.FormatNumber(this.reference);
    }

    public string Key
    {
        get { return this.reference; }
    }
}
```

### concrete-cross-module-dependency — in Go

A constructor takes another module's concrete service

```go
----------[ Bad ]----------

func NewNightAudit(
	audit *typeports.TillGraphService,
	receipts <-chan string,
	expected int,
) NightAudit {
	closed := make([]string, 0, expected)
	for ticket := range receipts {
		if ticket != "" {
			closed = append(closed, ticket)
		}
	}
	return NightAudit{source: audit, finished: closed}
}

----------[ Good ]----------

func NewReceiptJob(
	renderer receiptRenderer,
) *receiptJob {
	return &receiptJob{renderer: renderer}
}
```

The other 36 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=shared/dependencies` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `concrete-cross-module-dependency`, `guard-beside-transition`, `hand-rolled-registry`, `interface-overlap`, `lone-implementer-interface`, `middle-man`, `namespace-cycle`, `namespace-dependency`, `refused-bequest`, `train-wreck`, `undeclared-member`, `unread-collaborator`, `unused-private-member`, `useless-property-hook`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 41 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
