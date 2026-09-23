# Python enums — seal the set, put the knowledge on the case — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-constant-class-enum

a class that is nothing but `PENDING = "pending"` constants — a closed set of values written out by hand instead of an `Enum`

```py
----------[ Bad ]----------

class Carrier(object):
    POSTNL = "PNL"
    DHL = "DHL"

----------[ Good ]----------

class CarrierCode(StrEnum):
    POSTNL = "PNL"
    DHL = "DHL"
```

### python-enum-case-or-chain

`s == Status.PENDING or s == Status.LATE` — a group of an enum's members re-derived at the call site instead of named on the enum

```py
----------[ Bad ]----------

def needs_reminder(invoice) -> bool:
    return invoice.status == InvoiceStatus.OPEN or invoice.status == InvoiceStatus.OVERDUE

----------[ Good ]----------

def owes_money(invoice) -> bool:
    return invoice.status.is_unpaid
```
