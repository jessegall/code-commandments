### A record is a type

When the keys of a dict are known in advance — the same handful, read by name — the dict is a record,
and a record is a type:

```python
from dataclasses import dataclass


@dataclass(frozen=True)
class Line:
    sku: str
    quantity: int
    unit_price: int
```

The fields are declared once, the type checker sees every read, a missing field fails where the value
is built, and `frozen=True` means nobody changes it behind your back. Behaviour that reads only those
fields — a total, a label — becomes a method on it.

### Build it at the edge

Loose data arrives as dicts: JSON, a form, a row. Turn it into the type **where it enters** — one
`Line.from_payload(payload)` — and pass the type from there on. The rest of the program never sees
the dict, so it never has to wonder which keys are there.

### Values that always travel together are one value

Three parameters that every caller passes side by side — `street, city, postcode` — are an `Address`
waiting to be named. Give them one type and pass that.

### What is NOT this sin

- A dict used as a **mapping**: keys that are data (a SKU → stock level), iterated or looked up by a
  value you did not write in the source.
- `**kwargs` forwarded unchanged, and the dict a serializer hands you right before you convert it.