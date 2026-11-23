---
name: commandments-shared-dependencies
description: "Adding an import, a type hint, a `new` or a constructor parameter that crosses a module boundary (a namespace, a package, a folder), moving a class between modules, deciding where a new class belongs, declaring the project's layers, or adding an interface. Read this BEFORE you do, and when a dependencies, namespace-cycle or namespace-dependency finding points here."
---

# Dependencies — point one way, through contracts at the boundary

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Dependencies point one way: from the code that decides to the code it builds on, never back and never in a circle.
> Read this before you add an import, a type hint or a constructor parameter that crosses a module boundary, move a
> class between modules, or add an interface, and when a dependencies finding points here.
> The examples are pseudocode: a *module* is a namespace, a package or a folder of code that belongs together.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Depend downwards, on contracts at the boundaries, and on nothing you do not use.**

A module is useful only when it can be understood, tested and changed without dragging its neighbours along. Three
things destroy that:

1. **An arrow that points back up.** A low layer (shared primitives, the domain) that imports a high one (the UI, a
   feature) can no longer be used without it.
2. **A circle.** Two modules that import each other are one module with two names.
3. **A concrete class across a boundary.** A module that takes another module's concrete service is tied to how
   that service works inside. Across a boundary, depend on a contract.

Inside a module, the opposite holds: do not invent contracts nobody needs. An interface with one implementation and
no boundary to cross is indirection without a purpose.

```pseudo
// Bad: the domain reaches up into the UI
module Domain {
    import Ui.Toast   // ← the domain can no longer be used without the UI

    class Order {
        function pay() {
            …
            Toast.show("Paid!")
        }
    }
}

// Good: the domain says what happened, and the UI decides what to show
module Domain {
    class Order {
        function pay(): OrderPaid { … }
    }
}

module Ui {
    import Domain.Order

    Toast.show(messageFor(order.pay()))
}
```

## Declaring layers

Which layer may use which is the one thing here the code cannot say by itself, so a project declares it, in
`.commandments/config.json`. Each layer names the layers it may use; a reference out of a declared layer to any
other declared layer is a finding.

```json
"configure": {
    "php/NamespaceDependencyDetector": [
        {"layer": ["App\\Ui\\Elements"]},
        {"layer": ["App\\Ui\\Shared", ["App\\Ui\\Elements"]]},
        {"layer": ["App\\Domain"]}
    ]
}
```

Here the elements use only themselves, the shared layer is built from the elements, and the domain knows nothing
about the UI. Code outside every declared layer is not judged, and a layer contains its own sub-modules.

## The sins

### namespace-dependency

A declared layer references a layer it may not use: the arrow points back up, or sideways. Do not widen the
declaration to make the finding go away. Invert the arrow: the low layer takes the value or the contract it needs,
and the high layer supplies it.

```pseudo
// Bad: Ui.Elements may use only itself
module Ui.Elements {
    import Ui.Shared.Theme   // ← Elements may not use Shared

    class Button {
        function colour() { return Theme.current().primary }
    }
}

// Good: the element is given what it needs
module Ui.Elements {
    class Button {
        constructor(colour: Colour) { this.colour = colour }
    }
}
```

### namespace-cycle

Two modules import each other. Neither can be loaded, tested or reasoned about without the other. Move what they
share to a module both can use, so both arrows point the same way.

```pseudo
// Bad
module Orders   { import Invoices.Invoice }
module Invoices { import Orders.Order }   // ← each needs the other before it can load

// Good
module Billing  { interface Billable { function total(): Money } }
module Orders   { import Billing.Billable }
module Invoices { import Billing.Billable }
```

### concrete-cross-module-dependency

A constructor takes another module's concrete service. The two modules are now coupled through an implementation
that the other module should be free to change. Take a contract.

```pseudo
// Bad
module Checkout {
    import Payments.StripeGateway

    class PlaceOrder {
        constructor(gateway: StripeGateway) { … }   // ← tied to one implementation in another module
    }
}

// Good
module Checkout {
    import Payments.PaymentGateway

    class PlaceOrder {
        constructor(gateway: PaymentGateway) { … }   // ← a contract
    }
}
```

### lone-implementer-interface

An interface with exactly one implementation, in the same module, crossing no boundary. Every reader has to jump
through it to find the only code there is. Use the implementation directly. Keep an interface when it is a real
boundary: another module depends on it, or a second implementation exists.

```pseudo
// Bad
interface PriceCalculatorInterface {   // ← one implementation, no boundary: a jump for nothing
    function priceOf(cart): Money
}

class PriceCalculator implements PriceCalculatorInterface { … }   // ← the only code there is

class Checkout {
    constructor(calculator: PriceCalculatorInterface) { … }
}

// Good
class PriceCalculator { … }

class Checkout {
    constructor(calculator: PriceCalculator) { … }   // ← the class is used directly
}
```

### interface-overlap

An interface that repeats the signatures of a contract the project already has. Two contracts for one promise will
drift apart. Check that they promise the same behaviour, then reuse the existing one.

```pseudo
// Bad
interface Exportable  { function toRow(): Row }
interface CsvWritable { function toRow(): Row }   // ← the same promise as Exportable

// Good
interface Exportable  { function toRow(): Row }

function writeCsv(records: Exportable[]) { … }
```

### undeclared-member — in PHP

A member access that the receiver's declared type neither declares nor inherits. It works today because the object
that happens to arrive has the member; the type promises nothing. Declare the member on the contract the carrying
types share, and type the access with that contract.

```php
// Bad
public function label(Shape $shape): string
{
    return $shape->radius . ' cm';       // ← only Circle has a radius
}

// Good
interface Sized
{
    public function size(): string;
}

public function label(Sized $shape): string
{
    return $shape->size();
}
```

## In your language

- **PHP.** A module is a namespace; the arrow is a `use`, a type hint, a `new` or a static call.
- **Python.** A module is a package; an import inside a function, added to dodge a circular import, is the cycle
  still being there.
- **C#.** A module is a namespace or a project; project references already forbid cycles between assemblies, so
  watch the namespaces inside one.
- **TypeScript, Vue.** A module is a folder; `import type` still counts as an arrow.
- **Go.** A module is a package; the compiler refuses import cycles, and small interfaces are declared by the
  package that uses them.

## Commands

- `{{binary}} judge --skill=shared/dependencies` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `concrete-cross-module-dependency`, `interface-overlap`, `lone-implementer-interface`, `namespace-cycle`, `namespace-dependency`, `undeclared-member`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`shared/tell-dont-ask`](../tell-dont-ask/SKILL.md) — what to do instead of reaching into another module's objects.
