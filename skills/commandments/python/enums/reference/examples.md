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

### python-enum-value-match

`match status.value: case "paid": …` at a call site — the enum's raw values matched again where the enum could answer

```py
----------[ Bad ]----------

def badge(order) -> str:
    match order.payment.value:
        case "pending":
            return "amber"
        case "paid":
            return "green"
        case "refunded":
            return "grey"

----------[ Good ]----------

def badge_for(order) -> str:
    return order.payment.colour()
```

### python-match-wildcard-returns-none

a `match` over an enum's members whose `case _:` returns `None` — a member nobody handled answers nothing instead of failing

```py
----------[ Bad ]----------

def label_size(carrier: LabelCarrier) -> str | None:
    match carrier:
        case LabelCarrier.POSTNL:
            return "A6"
        case LabelCarrier.DHL:
            return "A5"
        case _:
            return None

----------[ Good ]----------

def label_size_for(carrier: LabelCarrier) -> str:
    match carrier:
        case LabelCarrier.POSTNL:
            return "A6"
        case LabelCarrier.DHL | LabelCarrier.UPS:
            return "A5"
        case _:
            raise UnhandledCarrier.of(carrier)
```
