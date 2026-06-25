# Named exceptions — static factories own the message

THE RULE: a throw site passes **domain values**, never strings. All message
assembly lives inside the exception class — in a static factory (preferred) or,
second best, its constructor. The factory's signature documents exactly what
context the failure needs, the throw reads as prose, and tests assert on the
exception **type** instead of matching message substrings.

## The core move

### Bad — a generic exception assembled at the throw site

```php
if ($port->required) {
    throw new RuntimeException(
        "Missing required input '{$port->name}' on node '{$this->nodeId}'",
    );
}
```

This names a failure CATEGORY, not a failure. It cannot be caught by type, the
message format is owned by the caller, and the moment a second site throws the
same failure the string gets duplicated and drifts.

### Good — a named exception with a static factory

```php
final class MissingRequiredInputException extends RuntimeException
{
    public static function for(string $port, string $nodeId): self
    {
        return new self("Missing required input '{$port}' on node '{$nodeId}'");
    }
}

if ($port->required) {
    throw MissingRequiredInputException::for($port->name, $this->nodeId);
}
```

The call site passes data; the exception builds its own sentence.

## The same rule applies to named exceptions

A named type with a leaked message is still wrong — the type is right but the
prose escaped its home:

```php
throw PortNotWiredException::for($name);          // righteous — factory builds the message
throw new PortNotWiredException($port, $node);    // acceptable — ctor builds the message
throw new PortNotWiredException("Port '...'");    // still a sin — message string leaked
```

## A factory does NOT launder a message

Handing the message string to a static factory is the same sin wearing a
`::make()` — the prose still lives at the call site, not in the exception:

```php
// sin — the factory is just a message courier:
throw InvalidPipeDefinitionException::make(
    $reflection->getName(),
    'SingleSubPipelineAdapter implementations must define mapTo()',
    'public function mapTo(): mixed',
);

// righteous — the call site passes DATA; the message is built inside:
throw InvalidPipeDefinitionException::missingMethod($reflection->getName(), 'mapTo');
```

A multi-word string argument to an exception factory is the tell that the
message leaked. The factory takes domain values (the class, the missing method)
and assembles the sentence itself.

## Factory conventions

- `::for(...)` is the default name. Purpose-named factories are even better when
  one exception covers variants: `CannotCoerceValueException::toInt($value)`,
  `...::toFloat($value)`.
- The factory returns `self` and is the ONLY place `new self(...)` with a message
  appears.
- Carry the domain values as typed public readonly properties next to the
  message — catchers get data, not a string to parse. Promote `$port`/`$nodeId`
  via a constructor that builds the message itself, or stash them on the instance
  in the factory.
- Extend the closest matching SPL class (`RuntimeException`,
  `InvalidArgumentException`, ...) or a package-level base exception so
  coarse-grained `catch` blocks keep working.

## Decision table

| Throw site | Verdict | Do |
|---|---|---|
| `throw new RuntimeException("...{$x}...")` | sin | Create a named exception; throw `Named::for($x)`. |
| `throw new NamedException("...{$x}...")` | sin | Move the message inside; throw `NamedException::for($x)`. |
| `throw NamedException::make($x, 'long message', ...)` | sin | The factory takes domain values only; build the message inside. |
| `throw NamedException::for($port, $node)` | righteous | — |
| `throw new NamedException($port, $node)` (ctor builds message) | acceptable | A static factory is still preferred. |
| `new self(...)` / `new static(...)` inside the exception's factory/ctor | righteous | That is the message's one home. |
| `throw $e` (rethrow) / exception received from elsewhere | righteous | — |
| `ValidationException::withMessages([...])` (vendor factory) | righteous | Already the prescribed pattern. |

## When to reach for it

- Any `throw` you are writing or reviewing: replace inline message assembly with
  a named exception + static factory taking domain values.
- A failure thrown from more than one site (the message would otherwise drift).
- A caller that needs to `catch` by type or read structured context off the
  exception instead of parsing a string.

## When to leave it

- `new self(...)` / `new static(...)` (or the class's own name) **inside** the
  exception's own factory or constructor — that is exactly where the string
  belongs.
- Rethrows (`throw $e`) and exceptions you received from elsewhere.
- A named exception constructed from domain values with **no** message string
  (`new PortNotWiredException($port, $node)`) — the constructor builds the
  message. A static factory is still preferred, but this is not a sin.
- Vendor factories that already take structured input
  (`ValidationException::withMessages([...])`).

## The instinct

Never type `throw new` followed by a string argument. Find (or create) the named
domain exception, give it a static factory taking the domain values, and throw
via the factory. If you are interpolating, concatenating, or `sprintf`-ing inside
a `throw` statement, stop — that string belongs inside the exception class.

Enforced by **PreferNamedExceptions**. Scripture:
`commandments:scripture --prophet=PreferNamedExceptions`.
