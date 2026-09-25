### A value that is always there is typed as always there

When a value is always present where it is used, the type says so: a required constructor parameter, a
non-nullable property set in the constructor, a `required` member, a field of a `record`. Declaring it
`T?` "because it is filled in later", or putting it on `this` for the length of one call, moves the
certainty onto every reader, who writes it back with `?.`, `??` or `!`. That defensive code is the
symptom; the cure is upstream, in the type.

### `!` and `= null!` are the same lie, told to the compiler

Nullable reference types let the compiler check absence for you. `value!` and `= null!` switch that check
off at exactly the place it was asked to help. If the value is always there, make the declaration
non-nullable and give it a value where the object is built. If it really can be missing, keep the `?` and
handle the missing case once, where it is decided.

### A required slot is filled with the real value

A `required` property or a non-nullable parameter says "the caller has this". Filling it with `""`, `0`
or `Guid.Empty` to get an object built hides a missing value where no type check can see it. Fetch the
real value, or split off a smaller type that promises only what you actually have.

### State for one call belongs to that call

Saving a field to a local, overwriting it for one operation and putting it back afterwards turns the
object into a scratch pad. The value is per call, so it is a parameter or a local, not a field.

### Not every `null` is a lie

This is the complement of `csharp/absence`. Absence says: when a value really can be missing, say so in
the type and decide what "missing" means where the value is made. Type honesty says: don't invent a
missing case the design does not have.