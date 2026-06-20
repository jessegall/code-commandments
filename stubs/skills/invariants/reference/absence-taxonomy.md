# The three kinds of absence

Behind every `?T` / `Option<T>` is one of three very different things. Naming
which one you have decides the tool — and getting it wrong either spreads
defensive ceremony everywhere or silently swallows a bug.

| Kind | What the `null` means | Right tool | Enforced by |
|---|---|---|---|
| **1. Genuine domain absence** | A value-or-nothing that is possible from *valid* input — an optional field, a real lookup miss, untrusted external data. | `{{ namespace }}\Option<T>` (or a Null Object) | `PreferOptionOverNull` pushes here |
| **2. No absence at all** | The value is *always* produced; the Option/nullable is pure ceremony. | Return `T` directly — no hedge | `NoOptionOveruse` |
| **3. Invariant violation** | The `none` can only happen if *we* made a mistake: an unhandled enum case, an unregistered handler, a "not found" the caller already established must exist. | **Fail loud** — throw a named exception, or make the method total | `ThrowOnUnhandledCase`, `PreferTotalOverNullable`, `NoSwallowedNotFound` |

This skill is about telling **3** apart from **1**. The test is a single
question:

> What does the absence *mean*? If "there may legitimately be no value" → kind 1,
> keep `Option`. If "we forgot to handle this / this should never happen" →
> kind 3, fail loud. Never let a should-crash bug become a silent empty value.

## Bad → good

### Bad — an invariant violation wearing an Option's coat

```php
// A closed enum: every real case maps to a renderer. The only `none` is the
// fallthrough — which can only fire if someone adds NodeType::C and forgets it.
/** @return Option<Renderer> */
public function rendererFor(NodeType $type): Option
{
    return match ($type) {
        NodeType::A => Option::some(new RendererA()),
        NodeType::B => Option::some(new RendererB()),
        default     => Option::none(),   // a forgotten case → silent none
    };
}
```

Every caller now `->getOrThrow()`s this. The Option promised "maybe no
renderer" — but no input ever validly produces none. It is an invariant
violation dressed up as the blessed pattern, and it gets waved through review
*because* it looks like `Option`.

### Good — fail loud so a forgotten case can't be silent

```php
// Drop the default → a new enum case is a compile-time match error.
public function rendererFor(NodeType $type): Renderer
{
    return match ($type) {
        NodeType::A => new RendererA(),
        NodeType::B => new RendererB(),
        NodeType::C => new RendererC(),
    };
}
```

### Contrast — genuine absence, where Option IS right

```php
// A user MAY legitimately not exist for this email — valid input, real miss.
/** @return Option<User> */
public function findByEmail(string $email): Option
{
    return Option::make($this->users[$email] ?? null);
}
```

Here the `none` is reachable from valid input, so kind 1 holds: keep the Option,
let the caller `->transform(...)->getOr(...)`.

## When to reach for "fail loud" (kind 3)

- A `match`/`switch` over a closed enum where every real case yields a value and
  the only `none` is the `default`/`null` arm — the none means "unhandled case".
- A method whose absence **no caller ever tolerates** — every call site does
  `?? throw`, `->getOrThrow()`, or a blind `->` deref.
- A lookup whose key the surrounding code already **established must exist** (an
  authenticated user id, a foreign key, a required template).

## When to leave it (kind 1 — keep the Option)

- The absence is possible from valid input: an optional lookup, untrusted
  external data, a real domain "not found".
- **Any** caller genuinely handles the absence — branches on null, supplies a
  real `?? $default`, uses `?->`, consumes the Option with `->getOr($default)` /
  `->transform(...)->getOr(...)`, or passes it on as still-optional.

In those cases the partiality is *earned* — keep modelling it as `Option<T>`
(never raw `null`), and read the `commandments-option` skill for the API.
