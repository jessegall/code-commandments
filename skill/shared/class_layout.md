> The top of a class lists everything the object holds, in the same order every time. The methods come after.
> Read this before you add a constant, a field or a property to a class, and especially before you add one next to
> the method that uses it, and when a class-layout finding points here.
> The examples are pseudocode: read them as the language you are writing.

## The principle

**Declare what a class has above what it does.**

A reader opens a class to learn two things, in this order: what does it hold, and what can it do. The first screen
answers the first question only if it answers it completely. One field declared two hundred lines down, beside the
method that uses it, means the list at the top can no longer be trusted, and every reader has to scan the whole
class to be sure they have seen all of its state.

The order is fixed, so it costs nothing to follow:

1. mixins and trait uses (they add members, so they are read first);
2. the cases, in an enum;
3. constants;
4. static fields;
5. instance fields, widest visibility first: public, protected, private;
6. computed properties, after the fields they read from;
7. the constructor, then the methods.

Within one group the order is yours. If the top of a class feels too long to read, the class holds too much: split
it. Do not shorten the list by scattering it.

```pseudo
// Bad: you learn what a Cart holds only by reading all of it
class Cart {
    function add(line) { this.lines.add(line) }

    private lines: Line[] = []

    function total() { return sum(this.lines) * (1 + TAX) }

    const TAX = 0.21
}

// Good
class Cart {
    const TAX = 0.21

    private lines: Line[] = []

    function add(line) { this.lines.add(line) }

    function total() { return sum(this.lines) * (1 + TAX) }
}
```

## The sins

### member-after-method

A constant, a field, a computed property, a trait use or an enum case declared below a method. The reader meets
the behaviour before the state it works on. Move it to the top, above the constructor.

```pseudo
// Bad
class Session {
    function start() { this.startedAt = now() }

    private startedAt: Time

    function isRunning() { return this.startedAt != null }
}

// Good
class Session {
    private startedAt: Time

    function start() { this.startedAt = now() }

    function isRunning() { return this.startedAt != null }
}
```

### member-out-of-order

A declaration at the top of a class that comes after something that belongs below it: a constant under a field, a
public field under a private one, a computed property above the fields it reads. Put them in the fixed order.

```pseudo
// Bad
class Customer {
    private notes: string[]
    get fullName() { return this.first + " " + this.last }
    public first: string
    public last: string
    const MAX_NOTES = 20
}

// Good
class Customer {
    const MAX_NOTES = 20

    public first: string
    public last: string
    private notes: string[]

    get fullName() { return this.first + " " + this.last }
}
```

## In your language

- **PHP.** `use` traits, `case`s, `const`, `static` properties, properties (public, protected, private), hooked
  properties, `__construct`, methods. Constructor promotion is state at the top already.
- **Python.** Class attributes, `ClassVar`s and dataclass fields come before the first `def`; a nested class or an
  annotated field is never placed below a method.
- **C#.** Constants, static fields, fields, then properties, then constructors, then methods.
- **TypeScript.** Static members, fields (public before private), accessors, the constructor, methods.
