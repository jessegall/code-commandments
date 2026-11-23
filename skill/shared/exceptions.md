> A failure should be loud, have a name, and carry where it started.
> Read this before you write a `throw`, a `try`/`catch`, or an exception type in any language, and when an
> exceptions finding points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Fail by name, at the source, and never turn a failure into an answer.**

A failure is information: what went wrong, to what, and where it began. Each rule here protects one part of it.

1. **A failure has a name.** Throw a type of its own (`OrderNotFound`), never the language's catch-all (`Exception`,
   `Error`, `RuntimeError`). A name is something a caller can catch, a test can expect, and a log can count.
2. **The failure writes its own sentence.** A named constructor takes the values involved (`OrderNotFound.withId(id)`)
   and builds the message once. The throw site passes facts, not prose, so the same failure reads the same everywhere.
3. **Catch only to do something.** Handle it, add what the caller needs, or release what was held. A `catch` that
   does nothing useful should not be written; the failure travels by itself.
4. **A failure never becomes a value.** An empty `catch`, or one that returns `null`, `false` or `[]`, turns "it broke"
   into something that looks like an answer. The caller carries on with a wrong answer.
5. **Wrapping keeps the cause.** When a caught failure is replaced by one of your own, the original goes along as its
   cause. Otherwise the trace of where it started is gone.

```pseudo
// Bad: the caller of load() can never tell "no settings" from "the disk is broken"
function load(): Settings? {
    try {
        return Settings.from(file.read(path))
    } catch (failure) {
        return null
    }
}

// Good: it either gives settings or says, by name, why it cannot
function load(): Settings {
    try {
        return Settings.from(file.read(path))
    } catch (failure: FileUnreadable) {
        throw SettingsUnavailable.at(path, cause: failure)
    }
}
```

## The sins

### swallow-catch

A `catch` whose body is empty, or only returns `null`/`false`/an empty value, or moves on. The failure becomes
something that looks like an answer. Let it throw, or surface it by name with its cause. Skipping a failure is only
right at one named boundary that logs it.

```pseudo
// Bad
function rate(currency): Rate? {
    try {
        return exchange.fetch(currency)
    } catch (failure) {
        return null   // ← the failure now looks like "no rate"
    }
}

// Good
function rate(currency): Rate {
    try {
        return exchange.fetch(currency)
    } catch (failure: ExchangeDown) {
        throw RateUnavailable.for(currency, cause: failure)
    }
}
```

### rethrown-unchanged

A `catch` whose whole body throws back what it caught. That is exactly what writing no clause does. Delete it, or
give it work.

```pseudo
// Bad
try {
    ledger.post(entry)
} catch (failure) {
    throw failure   // ← does exactly what writing no catch does
}

// Good: no clause at all
ledger.post(entry)

// Good: or a clause that does something
try {
    ledger.post(entry)
} catch (failure) {
    transaction.rollBack()

    throw failure
}
```

### wrapping-without-cause

A new failure thrown inside a `catch` without the caught one as its cause. Whoever reads the log sees your failure
and nothing of where it began.

```pseudo
// Bad
try {
    gateway.charge(order)
} catch (failure: GatewayTimeout) {
    throw PaymentFailed.for(order)   // ← the timeout that caused it is lost
}

// Good
try {
    gateway.charge(order)
} catch (failure: GatewayTimeout) {
    throw PaymentFailed.for(order, cause: failure)   // ← the cause goes along
}
```

### generic-exception

A throw of the language's catch-all failure. Nobody can catch it without catching everything, and its only content
is a string. Give the failure a type of its own.

```pseudo
// Bad
throw Exception("Order " + id + " not found")   // ← nothing to catch it by, only a string

// Good
throw OrderNotFound.withId(id)
```

### message-at-throw

A named failure whose message is still written as a string at the throw. The same failure then reads differently at
each throw site, and the values involved are lost inside a sentence. Pass the values to a named constructor that
writes the message once.

```pseudo
// Bad
throw OrderNotFound("No order with id " + id + " for customer " + customer.name)
// ↑ the sentence is written here, and again at the next throw

// Good
throw OrderNotFound.forCustomer(id, customer)

class OrderNotFound extends Failure {
    static function forCustomer(id, customer) {
        return OrderNotFound("No order with id " + id + " for customer " + customer.name)
    }
}
```

## In your language

- **PHP.** Named constructors are static factories (`OrderNotFound::withId($id)`); the cause is `previous: $e`.
- **Python.** The factory is a `@classmethod`; the cause is `raise PaymentFailed.for_order(order) from failure`. Never
  `except Exception: pass`.
- **C#.** A static factory on the exception type; the cause is the inner exception (`new PaymentFailed(order, e)`).
  Rethrow with a bare `throw;`, never `throw e;`.
- **TypeScript.** Extend `Error`; the cause is `new PaymentFailed(order, { cause: failure })`.
- **Go.** A failure is a returned `error`. Wrap with `fmt.Errorf("charging order %s: %w", id, err)` so the cause
  stays reachable, and never discard an `err` with `_`.
