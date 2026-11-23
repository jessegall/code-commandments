> A declaration is a promise to the next reader. A type, a signature, a class header and a property must each tell
> the truth about what is there.
> Read this before you widen a type to make something pass, add a check "just in case", keep a member "for later", or
> override a method to refuse it, and when a type-honesty finding points here.
> The examples are pseudocode: read them as the language you are writing.

## The principle

**What the code declares and what the code does must be the same thing.**

A reader trusts declarations so they do not have to read bodies. Each sin here is a declaration that says one thing
while the code does another:

- a **guard** for a case the type already rules out, or a **type** that hides a case the guard knows about;
- a **signature** that takes a value it never uses, or changes a value it never admits to changing;
- a **field** that is really a per-call input, saved and put back;
- a **subtype** that claims a contract and throws when asked to keep it;
- a **computed property** that computes nothing, and a **private member** nothing calls.

The fix is always to make the declaration true, never to add a second defence beside it. When a check and a type
disagree, one of them is wrong: find out which, and correct that one.

## The sins

### impossible-guard

A check for a missing value on a variable whose type says it is always there. One of the two is wrong, and both are
worth knowing. If the type is right, delete the guard. If the value really can be missing, say so in the type and
decide the absence where the value is born.

```pseudo
// Bad
function ship(order: Order) {
    if (order == null) {
        return
    }

    courier.book(order)
}

// Good: the type is right, so the guard goes
function ship(order: Order) {
    courier.book(order)
}
```

### filled-parameter

A method that returns nothing and writes a field of an object it was handed, while its caller reads that field
afterwards. The signature says the object goes in; nothing says it comes out changed. A method that works something
out gives it back.

```pseudo
// Bad
function fillCounts(state: ExportState, run: string): void {
    state.rows = api.countRows(run)
}

state = ExportState()
fillCounts(state, run)
print(state.rows)

// Good
function countedRows(run: string): int {
    return api.countRows(run)
}

state = ExportState(rows: countedRows(run))
```

### scratch-state-restore

A method saves one of its own fields to a local, overwrites the field, does its work, and restores it. The field is
not state of the object at all: it is an input of this one call. Pass it as a parameter.

```pseudo
// Bad
class Router {
    prefix: string

    function nest(segment, routes) {
        parent = this.prefix
        this.prefix = parent + "/" + segment

        try {
            return routes.map(route => this.prefix + "#" + route)
        } finally {
            this.prefix = parent
        }
    }
}

// Good
class Router {
    function nest(prefix, segment, routes) {
        nested = prefix + "/" + segment

        return routes.map(route => nested + "#" + route)
    }
}
```

### refused-bequest

A method that overrides or implements an inherited one only to throw. The class says "I am a Ledger" and then
refuses to be one. Split the contract so the class implements only what it can do, or hold the parent instead of
extending it.

```pseudo
// Bad
class ReadOnlyLedger extends Ledger {
    function post(amount) {
        throw LedgerIsReadOnly.forPosting(amount)
    }
}

// Good: it holds a ledger and offers only what a report may do
class LedgerReport {
    constructor(ledger: Ledger) { this.ledger = ledger }

    function balance() { return this.ledger.balance() }
}
```

### unused-parameter

A parameter the body never reads, on a method no override could need it for. Every caller still has to find a value
for it. Remove it and the argument at each call, or use it where it was meant to be used.

```pseudo
// Bad
function charge(token: string, amount: Money): string {
    return gateway.send(token)
}

// Good
function charge(token: string, amount: Money): string {
    return gateway.send(token, amount)
}
```

### unused-private-member

A private method nothing in its class calls, or a private field nothing reads. It is dead, and the class still
carries it. Delete it, or call it from where it was meant to be used.

```pseudo
// Bad
class Cart {
    private lines: Line[]
    private lastTouched: Time          // never read

    private function recalculate() { … }   // never called
}

// Good
class Cart {
    private lines: Line[]
}
```

### useless-property-hook

A property getter that reads nothing from the object. It yields the same value however the instance is built, so it
is a stored property wearing computed syntax. Store it. Keep a getter only when its body derives from the object's
state.

```pseudo
// Bad
class LabelDefaults {
    get maxWeight(): Weight {
        return Weight(23000)
    }
}

// Good
class LabelDefaults {
    maxWeight: Weight = Weight(23000)
}
```

### python-constant-property — in Python

The same sin in Python: an `@property` whose body never reads `self`.

```python
# Bad
class Parcel:
    @property
    def kind(self) -> str:
        return "box"

# Good
class Parcel:
    kind: ClassVar[str] = "box"
```

## In your language

- **PHP.** A getter is a property hook (`get => …`); a stored value is a plain (often `readonly`) property. A check
  that contradicts the type is `=== null` on a non-nullable parameter.
- **Python.** The impossible guard is `if x is None` on a parameter not typed `| None`; a per-call input kept on
  `self` and restored in a `finally` is the scratch state.
- **C#.** A getter that reads no instance state should be an auto-property with an initialiser, or a constant.
- **TypeScript.** `get x() { return 5 }` is a field; an `if (!order)` on a parameter typed `Order` is the impossible
  guard.
- **Go.** There is no inheritance to refuse, but an interface method implemented only to panic is the same sin:
  split the interface.
