> One method does one thing, and its name says which. A parameter that chooses between two behaviours is two methods
> sharing a name.
> Read this before you add a `bool` parameter that switches what a method does, or make a parameter optional so
> that leaving it out means something different, and when a behaviour-per-method finding points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Give each behaviour its own named method. Never let an argument pick the behaviour.**

`render(true)` tells a reader nothing. They must open the method to learn that `true` means "compact", and inside
they find one `if` that splits the body into two halves that share nothing but a name. The caller always knows
which half it wants: it wrote the literal. So the choice can be made by name, at the call site, where it is
readable: `renderCompact()`.

The same holds for a parameter made optional so that leaving it out means "all of them" or "the other mode". The
absence is a flag too.

What the two halves really share goes into a private method both call.

```pseudo
// Bad
function render(compact: bool) {
    if (compact) {
        return this.title + " (" + this.count + ")"
    }

    return this.header() + this.rows() + this.footer()
}

label = widget.render(true)   // ← true means what?

// Good
function renderCompact() {
    return this.title + " (" + this.count + ")"
}

function renderFull() {
    return this.header() + this.rows() + this.footer()
}

label = widget.renderCompact()
```

## The sins

### flag-argument

A method whose whole body branches on a `bool` parameter, or on whether an optional one was given. Split it into
two methods, each named for what it does.

```pseudo
// Bad
function export(orders, asCsv: bool) {
    if (asCsv) {   // ← the flag splits the body in two
        return csv(orders.map(order => order.toRow()))
    }

    return json(orders.map(order => order.toMap()))
}

function archive(customer: Customer? = null) {
    if (customer == null) {   // ← leaving the argument out picks another behaviour
        archiveEveryone()
        return
    }

    archiveOne(customer)
}

// Good
function exportCsv(orders)  { return csv(orders.map(order => order.toRow())) }
function exportJson(orders) { return json(orders.map(order => order.toMap())) }

function archive(customer: Customer) { … }
function archiveAll() { … }
```

## In your language

- **PHP, C#, TypeScript, Go.** Two methods. A `bool` that only adjusts one detail of a single behaviour is a named
  option or an enum parameter, not a flag: `sort(Direction.Descending)`.
- **Python.** A keyword-only `bool` (`render(compact=True)`) reads better at the call site and is still two methods
  when the body is one `if` on it.
