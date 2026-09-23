# Python documentation — concise, present-tense, rare — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-archaeology-comment

a comment or docstring narrating the code's history — where it lived, what it replaced, what it no longer is

```py
----------[ Bad ]----------

def next_number(last: int, prefix: str) -> str:
    return f"{prefix}-{last + 1:06d}"

----------[ Good ]----------

def next_invoice_number(last: int, prefix: str) -> str:
    # six digits, because the accounting export pads to a fixed width
    return f"{prefix}-{last + 1:06d}"
```

### python-bloated-docblock

a class docstring of two or more paragraphs of prose — an essay that says the class does too much

```py
----------[ Bad ]----------

@dataclass(frozen=True)
class DeliveryWindow:
    """The hours a courier may arrive at an address.

    It also knows which carriers serve the postcode, how the slot is priced at peak times, and when the
    warehouse has to have the parcel packed for it.
    """

    opens: int
    closes: int

----------[ Good ]----------

@dataclass(frozen=True)
class CourierWindow:
    """The hours a courier may arrive at an address.

    Attributes:
        opens: the first hour, 0-23.
        closes: the last hour, 0-23.
    """

    opens: int
    closes: int
```

### python-ceremony-docblock

a docstring with no summary whose every entry restates the annotated signature — `order (Order):`, `:rtype: int`

```py
----------[ Bad ]----------

def quote(parcel: Parcel, zone: str) -> int:
    """
    Args:
        parcel (Parcel):
        zone (str):

    Returns:
        int
    """
    return 500 + parcel.weight_grams // 100

----------[ Good ]----------

def quote_cents(parcel: Parcel, zone: str) -> int:
    """The price of sending the parcel to the zone, in cents.

    Args:
        zone: a carrier zone code, such as "EU-1".
    """
    return 500 + parcel.weight_grams // 100
```

### python-dangling-doc-reference

a Sphinx cross-reference in a docstring (`:class:`shop.cart.Basket``) to a first-party name the codebase no longer declares

```py
----------[ Bad ]----------

def manifest(parcels: list) -> str:
    """One line per :class:`shop.dispatch_desk.Package` handed to the courier."""
    return "\n".join(str(parcel) for parcel in parcels)

----------[ Good ]----------

def manifest_lines(parcels: list) -> str:
    """One line per :class:`shop.dispatch_desk.Parcel` handed to the courier."""
    lines = [f"{index}. {parcel}" for index, parcel in enumerate(parcels, start=1)]
    return "\n".join(lines)
```

### python-negative-space-comment

a comment or docstring defending the code against a reading nobody made — what it is not, rather than what it is

```py
----------[ Bad ]----------

def backoff(attempt: int) -> int:
    return 2 ** attempt

----------[ Good ]----------

def retry_delay(attempt: int) -> int:
    return min(2 ** attempt, 60)
```

### python-restated-comment

a `#` comment that only narrates the statement below it — every word of it already spelled by the code

```py
----------[ Bad ]----------

def amount(self) -> int:
    # set the total to the lines sum
    total = sum(self.lines)
    return round(total * self.rate)

----------[ Good ]----------

def rounded_amount(self) -> int:
    # the tax office rounds each invoice once, never per line
    subtotal = sum(self.lines)
    return round(subtotal * self.rate)
```
