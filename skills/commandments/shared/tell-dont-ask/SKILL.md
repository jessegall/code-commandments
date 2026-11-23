---
name: commandments-shared-tell-dont-ask
description: "Writing a method that loops over or queries another object's collection or fields to work out something that object could answer, checking an object's state and throwing before telling it to change, a call chain through several of your own types (`order.customer().address().city()`), looking a fact about an object up by its id, or a class whose methods only forward to one field. Read this BEFORE you write any of them, and when a tell-dont-ask finding points here."
---

# Tell, don't ask — behaviour lives with its data

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Behaviour belongs with the data it works on. Tell an object what you want; do not ask for its insides and do the
> work yourself.
> Read this before you write a method that loops over another object's collection, checks another object's state
> before changing it, walks through an object to its neighbours' neighbours, or wraps a collaborator without adding
> anything, and when a tell-dont-ask finding points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Put the method on the object whose data it uses.**

When a method reads another object's fields, decides something from them and acts, three things have gone wrong at
once: the rule lives far from the data it is about, every other caller must repeat it, and the object cannot change
its insides without breaking strangers. Move the method to the data, and the caller shrinks to one sentence that
says what it wants.

A quick test: in the method you are writing, which object's name do you type most? If it is not `this`, the method
is probably in the wrong class.

```pseudo
// Bad: the caller pulls the order apart and does the order's work
total = 0
for (line in order.lines) {   // ← the caller does the order's arithmetic
    total = total + line.price * line.quantity
}
if (order.coupon != null) {
    total = total - order.coupon.discountOn(total)
}

// Good: the order answers
total = order.total()
```

## The sins

### feature-envy

A method that loops over or queries one other object's internals to work out something that object could answer
itself. The behaviour is in the wrong class. Move it onto the object it works on.

```pseudo
// Bad
class GraphPrinter {
    function edgesOf(node: Node): Edge[] {
        edges = []
        for (port in node.ports) {   // ← walks the inside of a Node from outside it
            for (edge in port.edges) {
                edges.add(edge)
            }
        }
        return edges
    }
}

// Good
class Node {
    function edges(): Edge[] {
        return this.ports.flatMap(port => port.edges)
    }
}
```

### keyed-lookup-envy

The indirect form: a method takes an object's identity and uses it as a key to look up a fact about that object
through a collaborator. The object is right there and could be asked.

```pseudo
// Bad
function label(product: Product): string {
    return this.stock.levelFor(product.id) > 0 ? "In stock" : "Sold out"
    // ↑ looks a fact about the product up by its id, with the product in hand
}

// Good
function label(product: Product): string {
    return product.isInStock() ? "In stock" : "Sold out"
}
```

### guard-beside-transition

A caller checks an object's state, throws if it is wrong, then tells the object to change. The rule for when the
change is allowed sits outside the change it guards, so the next caller can forget it. The method that changes the
state carries the rule and refuses by itself.

```pseudo
// Bad
if (sample.status != Status.Pending) {   // ← the rule for approve() stands outside approve()
    throw SampleRefused.notPending(sample)
}

sample.approve()

// Good
sample.approve()

class Sample {
    function approve() {
        if (this.status != Status.Pending) {
            throw SampleRefused.notPending(this)
        }

        this.status = Status.Approved
    }
}
```

### train-wreck

A call chain through three of the project's own types. The caller now depends on how four objects are wired
together, and a change three objects away breaks it. Ask the object you hold for the answer you want, and let each
type ask only its own neighbour.

```pseudo
// Bad
city = order.customer().address().city()   // ← knows how three other types are wired together

// Good
city = order.shippingCity()

class Order {
    function shippingCity() { return this.customer.city() }
}

class Customer {
    function city() { return this.address.city }
}
```

### middle-man

A class whose every public method only hands its arguments, unchanged, to the same field. It adds nothing between
the caller and the object that does the work. Let callers talk to that object, or give the class real behaviour.

```pseudo
// Bad
class OrderService {
    constructor(orders: OrderRepository) { this.orders = orders }

    function find(id)    { return this.orders.find(id) }   // ← only forwards
    function save(order) { return this.orders.save(order) }
    function all()       { return this.orders.all() }   // ← and so does every other method
}

// Good: the caller takes the repository itself
class CheckoutController {
    constructor(orders: OrderRepository) { this.orders = orders }
}
```

## In your language

- **PHP.** Watch for a `foreach` over `$other->items` and for `$a->b()->c()->d()` across your own classes. A fluent
  builder or a collection pipeline returning the same type is not a train wreck.
- **Python.** The envy is a function taking an object and reading three of its attributes; an `isinstance` ladder that
  asks what a value is before acting belongs on the types as a method.
- **C#.** LINQ over another object's collection, written outside that object, is the usual form: give the owner a
  method that returns the answer.
- **TypeScript.** `a.b?.c?.d` across your own types is the same chain with `?.` added.
- **Go.** A function in one package reaching through another package's struct fields: give that type a method.

## Commands

- `{{binary}} judge --skill=shared/tell-dont-ask` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `feature-envy`, `guard-beside-transition`, `keyed-lookup-envy`, `middle-man`, `train-wreck`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`shared/pass-the-object`](../pass-the-object/SKILL.md) — the same idea at a signature: give the callee the object, not pieces of it.
- [`shared/enums`](../enums/SKILL.md) — behaviour keyed off a case lives on the enum.
