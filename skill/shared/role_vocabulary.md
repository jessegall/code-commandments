> Three shapes come back in every codebase: a keyed store, a membership set, and a first-match dispatch. Each has a
> name and a contract. Use the name, keep the contract, and write the plumbing once.
> Read this before you write a class that keeps things under a key, a class that collects and iterates, or an
> if/else-if chain that picks the first matching handler, and before you name a class `…Registry`, `…Set` or
> `…Resolver`, and when a role-vocabulary finding points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**If a class is one of these shapes, name it for the role and build it on the one base. If it is named for a role,
it must behave like one.**

| Role | What it is | Contract |
|---|---|---|
| **Registry** | a keyed store | `register(key, item)`, `get(key)` gives the item or fails by name, `has(key)`, `all()` |
| **Set** | membership and iteration, no keys | `add(item)`, `has(item)`, `all()`; no lookup by key |
| **Resolver** | first-match dispatch | runs its predicates in order and gives the first match's result |

The relationship runs both ways. A class that fills a map under a key and throws on a missing key is a Registry
whether it says so or not: name it one and extend the project's `Registry` base, so `register`, `get` and `has` are
written once for the whole project. And a class called `HandlerRegistry` makes a promise to every reader: `get`
never returns nothing, and it does not also resolve, query or assemble. A role class does one job.

When a role has to decide "is this one of mine?", it asks the type (a marker interface), never a list of class
names kept by hand.

## The sins

### hand-rolled-registry

A class that writes its own keyed store: a private map, a method that fills it under a key, a getter that throws on
a missing key. Every such class repeats the same twenty lines and gets the miss slightly different. Extend the one
`Registry` base and state only what the registry holds.

```pseudo
// Bad
class PaymentHandlers {
    private handlers: map<string, PaymentHandler> = {}   // ← the same store every registry writes again

    function register(method: string, handler: PaymentHandler) {
        this.handlers[method] = handler
    }

    function get(method: string): PaymentHandler {
        if (method not in this.handlers) {
            throw UnknownPaymentMethod.named(method)
        }

        return this.handlers[method]
    }

    function has(method: string): bool {
        return method in this.handlers
    }
}

// Good: the plumbing lives in the base, once
class PaymentHandlerRegistry extends Registry<string, PaymentHandler> {
// ↑ it only says what it holds
}
```

## In your language

- **PHP.** `final class PaymentHandlerRegistry extends Registry`, with the item type in a `@extends` docblock. A
  project without a `Registry` base writes one first: a small abstract class, written once.
- **Python.** A generic base class, `class Registry(Generic[K, V])`; a plain `dict` passed around is not a Registry,
  because nothing names the miss.
- **C#.** `abstract class Registry<TKey, TItem>`; `get` throws a named exception, a separate `TryGet` may answer
  with a `bool`.
- **TypeScript.** `class PaymentHandlerRegistry extends Registry<Method, PaymentHandler>`; a `Map` exported from a
  module is the hand-rolled form.
- **Go.** A generic `Registry[K comparable, V any]` struct embedded in the concrete type.
