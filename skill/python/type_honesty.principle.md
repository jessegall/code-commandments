### A certain value is typed certain

When a value is always present where it is used, the annotation says so: a required parameter, an
attribute set in `__init__` and never `None`, a field of a frozen dataclass. Hedging it as `X | None`
"because it is filled in later", or keeping per-call state on `self`, pushes the certainty back onto
every reader, who re-establishes it with defensive code. The defensive code is the symptom; the cure is
upstream, in the type.

### Not every `None` is a lie

This is the complement of `python/absence`. Absence says: model a value that is genuinely missing
honestly — `None` with a check where it is born, an empty collection, a raise. Type honesty says: do not
manufacture a missing value the design does not have. A collaborator that may legitimately be absent and
is injected once is an absence decision, not a lie.

### The tell

You are re-proving, on every read, something the design already guarantees: `x.y if x else <fake>` on
your own attribute, a fallback branch that cannot be reached, a `previous = self.x … self.x = previous`
round trip. Ask whether the value is ever actually absent here. If it is not, the annotation is lying —
move the value into the signature, a required attribute or a value object, and delete the defence.