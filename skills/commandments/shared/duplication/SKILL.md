---
name: commandments-shared-duplication
description: "Read before copying a function, a compound condition, or a path to the same folder."
---

# Duplication: give repeated code one owner

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Code that represents one decision, mechanism or folder should say what it means in one place.

## The principle

Put the shared code where its data or responsibility lives. Pass the values that differ, and have each caller ask the owner for the answer.

## Rules

- [ ] Accept the value the callers already have and let the callee own the conversion.
      _Take the original typed value and convert it where it is used._
- [ ] Put the steps that define one job in a single function.
      _Extract the shared work, pass the differences as inputs, and call it from both sites._
- [ ] Write a shared function and call it from the places that need it.
      _Move the copied body to one owner and delegate to it from each caller._
- [ ] Keep the declarations as the source of truth for the classes that belong to the group.
      _Discover the implementations from their shared type, or let each class register itself._
- [ ] Share the body and pass what differs as parameters.
      _Extract the common function and give its varying inputs names._
- [ ] Name a folder in one place and share that name wherever code reaches it.
      _Put the folder in a shared constant or a method and use it to build paths within that folder._
- [ ] Give a repeated condition one name and have the callers use it.
      _Put a named predicate on the object that owns most of the condition and use it at each site._
- [ ] Give a repeated type test one name and use it at each site.
      _Extract the combined test to a predicate that describes which values it accepts._
- [ ] Use the vocabulary the parameter already has.
      _Replace the string with the named constant that carries the same value._

## Worked example

### converted-argument — in PHP

Most callers convert a value the same way for one parameter

```php
----------[ Bad ]----------

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

----------[ Good ]----------

// in Shop\Wire\HotkeyBinding
// The FIX: the parameter is declared in the currency the caller holds, and the conversion lives on
// the far side of the call.

public function bindDirect(string $node): WireMessage
{
    return WireMessage::raiseFor(HotkeyPressed::class, $node);
}

// in Shop\Wire\WireMessage
public static function raiseFor(string $signal, string $target): self
{
    return new self(SignalAlias::of($signal), $target);
}
```

### converted-argument — in TypeScript

Most callers convert a value the same way for one parameter

```ts
----------[ Bad ]----------

// in freight-arguments.ts
export function freightLabels(first: FreightArgument, second: FreightArgument): string[] {
    const one = freightLabel(freightValue(first))
    const two = freightLabel(freightValue(second))
    return [one, two]
}

// in freight-manifest.ts
export function publishManifest(items: FreightArgument[], print: (label: string) => void): void {
    for (const item of items) {
        if (item.insured) {
            print(freightLabel(freightValue(item)))
        }
    }
}

// in rebate-arguments.ts
export function rebatesByPart(claims: Map<string, RebateClaim>): Map<string, string> {
    const displayed = new Map<string, string>()
    for (const [name, claim] of claims) {
        displayed.set(name, formattedCredit(claimedCents(claim)))
    }
    return displayed
}

// in rebate-arguments.ts
export function describeLatestRebate(order: RebateClaim | undefined): string {
    if (order === undefined) return 'none issued'
    return formattedCredit(claimedCents(order))
}

// in registry-arguments.ts
export function registerSigns(
    slots: Signatory[], lookup: (name: string) => void, start: number,
): number {
    let written = start
    for (let offset = slots.length - 1; offset >= start; offset--) {
        const holder = slots[offset]
        lookup(registerCode(signatureOf(holder)))
        written++
    }
    return written
}

// in registry-arguments.ts
export function firstSigned(held: Signatory, room: string): string {
    return room + '/' + registerCode(signatureOf(held))
}

----------[ Good ]----------

function labelFor(details: LabelDetails): string {
    return details.code.trim()
}
```

### converted-argument — in Python

Most callers convert a value the same way for one parameter

```py
----------[ Bad ]----------

# in ledger_dump.py
def dump(entries: list[LedgerEntry]) -> str:
    lines = []
    for entry in entries:
        lines.append(ledger_line(reference=str(entry.number), amount=entry.amount))
    return "\n".join(lines)

# in ledger_posting.py
def post(self, entry: LedgerEntry) -> None:
    self.printed.append(ledger_line(str(entry.number), entry.amount))

# in parcel_weighing.py
def labels(readings: list[ScaleReading]) -> list[str]:
    return [weight_label(float(reading.display)) for reading in readings]

# in parcel_weighing.py
def heaviest_label(readings: list[ScaleReading]) -> str:
    return weight_label(kilos=float(max(readings, key=lambda reading: float(reading.display)).display))

# in tip_cents.py
def ring_up(jar: TipJar, typed: str) -> None:
    jar.add(Cents.parse(typed))

# in tip_cents.py
def ring_up_all(jar: TipJar, entries: list[str]) -> None:
    for typed in entries:
        jar.add(cents=Cents.parse(typed))

----------[ Good ]----------

# in ledger_posting.py
def post_honestly(self, entry: LedgerEntry) -> None:
    self.printed.append(ledger_line_for(entry))

# in ledger_lines.py
def ledger_line_for(entry: LedgerEntry) -> str:
    return f"#{str(entry.number).zfill(6)} {entry.amount}"
```

### converted-argument — in C#

Most callers convert a value the same way for one parameter

```cs
----------[ Bad ]----------

// in Barcodes.cs
public string Top(Parcel parcel) => barcodes.Encode(parcel.Id.ToString());

// in Barcodes.cs
public IEnumerable<string> All(IEnumerable<Parcel> parcels) =>
    parcels.Select(parcel => barcodes.Encode(parcel.Id.ToString()));

----------[ Good ]----------

public string Encode(Guid parcelId) => $"*{parcelId.ToString("N").ToUpperInvariant()}*";
```

### converted-argument — in Go

Most callers convert a value the same way for one parameter

```go
----------[ Bad ]----------

// in freight_arguments.go
func freightLabels(first FreightArgumentCode, second FreightArgumentCode) [2]string {
	one := freightLabel(string(first))
	two := freightLabel(string(second))
	return [2]string{one, two}
}

// in freight_collection.go
func freightCollectionPrice(invoice FreightArgumentCode) string {
	return freightLabel(string(invoice))
}

----------[ Good ]----------

func composeCheckoutLabel(label checkoutLabel) string {
	return label.code + " checkout"
}
```

The other 40 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=shared/duplication` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `converted-argument`, `divergent-twin`, `duplicate-function`, `list-mirrors-folder`, `near-duplicate-function`, `repeated-folder-path`, `repeated-guard`, `repeated-type-guard`, `unnamed-vocabulary-literal`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 45 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
