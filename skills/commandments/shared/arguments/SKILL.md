---
name: commandments-shared-arguments
description: "Read before adding a parameter, passing pieces of a value, or writing a result through an argument."
---

# Arguments: name the input and return the result

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A signature shows what the function needs and what the caller gets back.

## The principle

Resolve a lookup at the caller when the callee only needs the result. Give distinct operations distinct names.

## Rules

- [ ] Give the fields a named record type.
      _Give the fields a named record type._
- [ ] Declare the fields in a named parameter type.
      _Declare the fields in a named parameter type._
- [ ] Return a named record type.
      _Return a named record type._
- [ ] Write the template in the same shape as its output.
      _Write the template in the same shape as its output._
- [ ] Name the method as a question about its state.
      _Name the method as a question about its state._
- [ ] Pass the object that owns the Boolean decisions.
      _Pass the object that owns the Boolean decisions._
- [ ] Run the action in a named operation after construction.
      _Run the action in a named operation after construction._
- [ ] Give the fields a type that owns their shared behavior.
      _Give the fields a type that owns their shared behavior._
- [ ] Pass a value that owns the group of fields.
      _Pass a value that owns the group of fields._
- [ ] Pass the object and read its pieces where they are needed.
      _Pass the object and read its pieces where they are needed._
- [ ] Move the operation next to the data it uses.
      _Move the operation next to the data it uses._
- [ ] Return the result from the function.
      _Return the result from the function._
- [ ] Give each operation its own name.
      _Give each operation its own name._
- [ ] Use the type's copy operation to carry unchanged fields.
      _Use the type's copy operation to carry unchanged fields._
- [ ] Expose the operation a caller needs or return a snapshot.
      _Expose the operation a caller needs or return a snapshot._
- [ ] Put the lookup next to the type that owns the key.
      _Put the lookup next to the type that owns the key._
- [ ] Handle the expected cases explicitly and signal an unexpected value.
      _Handle the expected cases explicitly and signal an unexpected value._
- [ ] Move the field above the constructor and methods.
      _Move the field above the constructor and methods._
- [ ] Keep constants above instance fields.
      _Keep constants above instance fields._
- [ ] Give the state a lifetime and an owner.
      _Give the state a lifetime and an owner._
- [ ] Return a new value for the changed state.
      _Return a new value for the changed state._
- [ ] Name the command as an instruction.
      _Name the command as an instruction._
- [ ] Pass the resolved value to the function.
      _Pass the resolved value to the function._
- [ ] Name the return slots or return a named record.
      _Name the return slots or return a named record._
- [ ] Let the expression supply its return type.
      _Let the expression supply its return type._
- [ ] Keep temporary data local to the operation.
      _Keep temporary data local to the operation._
- [ ] Remove the unused parameter from the signature and its callers.
      _Remove the unused parameter from the signature and its callers._

## Worked example

### array-bag — in TypeScript

A loose map is read through fixed keys

```ts
----------[ Bad ]----------

export function hasRefund(
    slips: Record<string, number>,
): boolean {
    if (slips['paid'] === 0) {
        return false
    }
    return slips['refunded'] > 0
}

----------[ Good ]----------

export function makeAddress(street: string, district: string, postcode: string): AddressParts {
    return { street, district, postcode }
}
```

### array-bag — in Go

A loose map is read through fixed keys

```go
----------[ Bad ]----------

func discardsDeposit(
	slips map[string]int,
) bool {
	if slips["deposit"] == 0 {
		return true
	}
	return slips["collected"] < slips["cancellation"]
}

----------[ Good ]----------

func resolvedAddress(street string, district string, postcode string) shippingAddress {
	return shippingAddress{street: street, district: district, postcode: postcode}
}
```

The other 54 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=shared/arguments` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `array-bag`, `array-bag-parameter`, `array-return-bag`, `assembled-template`, `bare-state-predicate`, `computed-boolean-argument`, `constructor-side-effect`, `coupled-fields`, `data-clump`, `derived-argument`, `feature-envy`, `filled-parameter`, `flag-argument`, `hand-rolled-wither`, `handed-out-collection`, `keyed-lookup-envy`, `match-default-returns-null`, `member-after-method`, `member-out-of-order`, `mutable-static-state`, `mutable-value-object`, `narrated-command`, `param-resolved-from-param`, `positional-tuple-return`, `redundant-arrow-return-type`, `scratch-state-restore`, `unused-parameter`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 56 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
