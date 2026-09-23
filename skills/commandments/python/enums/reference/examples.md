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

### python-enum-value-match

`match status.value: case "paid": …` at a call site — the enum's raw values matched again where the enum could answer

```py
----------[ Bad ]----------

def refund_days(order) -> int:
    match order.channel.value:
        case "web" | "phone":
            return 30
        case "shop":
            return 14

----------[ Good ]----------

def refund_window(order) -> int:
    return order.channel.refund_days
```

### python-in-literals-mirrors-enum

`x in ("pending", "late")` whose literals are an existing enum's values — a group of its members spelled as raw strings at the call site

```py
----------[ Bad ]----------

def route(self, ticket) -> None:
    if ticket.priority not in ["low", "normal"]:
        self.oncall.page(ticket.number)

----------[ Good ]----------

def route_by_priority(self, ticket) -> None:
    if not Priority(ticket.priority).can_wait:
        self.oncall.page(ticket.number)
```
