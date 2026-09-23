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
