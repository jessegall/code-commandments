---
name: commandments-shared-documentation
description: "Read before writing a comment or changing a declaration's documentation."
---

# Documentation: explain the code as it is

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A useful comment tells the reader why the code takes its shape or what contract a caller can rely on.

## The principle

Use names and types for what the code already says. Keep decisions and their history in the change record, and give any explanation that belongs in the source one place near its declaration.

## Rules

- [ ] Explain why the code is written this way today.
      _Move the history to the change record._
- [ ] Keep the type description short.
      _Move supporting detail to the place where it is needed._
- [ ] Keep documentation for information the signature does not already say.
      _Remove the redundant tags and leave any useful explanation._
- [ ] Keep references tied to existing declarations.
      _Correct the target or remove the reference._
- [ ] Say what the code does.
      _Keep a comment only when it explains a real constraint._
- [ ] Give the statement clear names and let it speak for itself.
      _Delete the comment or explain the reason for the statement._
- [ ] Keep one doc comment per declaration.
      _Merge useful text into one doc comment._

## Worked example

### archaeology-comment — in PHP

A comment narrates the history of the code

```php
----------[ Bad ]----------

public function handle(array $event): void
{
    // formerly lived inline in the StripeController; was extracted here
    $type = $event['type'];

    $this->record($type, $event['id'] ?? throw new \InvalidArgumentException('event id is required'));
}

----------[ Good ]----------

public function handleRefund(array $event): void
{
    // a refund carries no id of its own, so the charge reference identifies the row
    $this->record('refund', $this->reference($event));
}
```

### archaeology-comment · negative-space-comment · restated-comment — in TypeScript

_archaeology-comment_ — A comment narrates the history of the code

_negative-space-comment_ — A comment defends the code against an assumption the reader has no reason to make

_restated-comment_ — A comment repeats the words of the statement it documents

```ts
----------[ Bad ]----------

export function drawerBalance(drawer: { balance: number }): number {
    // Previously this calculation used a cached value.
    const amount = drawer.balance
    // The currency is deliberately not rounded here.
    const calculated = Math.abs(amount)
    // Return calculated amount.
    return calculated + amount
}

----------[ Good ]----------

export function drawerForDisplay(amount: number): number {
    return Math.abs(amount)
}
```

### archaeology-comment — in Python

A comment narrates the history of the code

```py
----------[ Bad ]----------

# formerly lived in the checkout module, before invoices had their own
def next_number(last: int, prefix: str) -> str:
    return f"{prefix}-{last + 1:06d}"

----------[ Good ]----------

def next_invoice_number(last: int, prefix: str) -> str:
    # six digits, because the accounting export pads to a fixed width
    return f"{prefix}-{last + 1:06d}"
```

### archaeology-comment — in C#

A comment narrates the history of the code

```cs
----------[ Bad ]----------

// formerly lived in the checkout service, and was extracted here
public static int Days(bool member) => member ? 60 : 30;

----------[ Good ]----------

/// <summary>Members get longer to decide, since they return far less often.</summary>
public static int DaysFor(bool member) => member ? 60 : 30;
```

### archaeology-comment · negative-space-comment · restated-comment — in Go

_archaeology-comment_ — A comment narrates the history of the code

_negative-space-comment_ — A comment defends the code against an assumption the reader has no reason to make

_restated-comment_ — A comment repeats the words of the statement it documents

```go
----------[ Bad ]----------

func drawerBalance(amount int) int {
	// Previously this calculation used a cached value.
	calculated := amount + 1
	// The adjustment is deliberately not rounded here.
	adjusted := calculated * 2
	// Return adjusted amount.
	return adjusted + amount
}

----------[ Good ]----------

func drawerForDisplay(amount int) int {
	return amount + 1
}
```

The other 22 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=shared/documentation` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `archaeology-comment`, `bloated-docblock`, `ceremony-docblock`, `dangling-doc-reference`, `negative-space-comment`, `restated-comment`, `stacked-docblock`.
- `{{binary}} repent --sin=<sin>` — auto-fix, for `stacked-docblock`. Review it with `--dry-run` first.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 27 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
