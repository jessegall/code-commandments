### Seal the set

A value that can only ever be one of a handful — `"paid"`, `"pending"`, `"refunded"` — is an
`Enum` (or a `StrEnum` where it must still read as its string on the wire):

```python
from enum import StrEnum


class Status(StrEnum):
    PENDING = "pending"
    PAID = "paid"
    REFUNDED = "refunded"

    def is_settled(self) -> bool:
        return self in (Status.PAID, Status.REFUNDED)
```

A typo is now an `AttributeError` where it is written, the type checker sees every case, and
there is one place to read the whole set.

### Put the knowledge on the case

The per-case answers — a label, a colour, "is this final?" — are methods on the enum, written once
with every case in view. A comparison against string literals at a call site is that method,
homeless: the next call site writes it again, a little differently.

### Parse once, at the edge

A string arriving from JSON, a form or a database becomes the enum where it enters —
`Status(payload["status"])` — and fails there if it is not one of the cases. From then on the code
passes the enum, never the string.