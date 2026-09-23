# Python method mood — an order, or a question — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-bare-state-predicate

a `bool` about the object's own state named as a bare verb — `binds()`, `spins` — where a question belongs

```py
----------[ Bad ]----------

def locks(self) -> bool:
    return self.bolted

----------[ Good ]----------

class LockerHatch:
    def __init__(self, bay: str, bolted: bool) -> None:
        self.bay = bay
        self.bolted = bolted

    def is_locked(self) -> bool:
        return self.bolted
```

### python-narrated-command

a command named in the third person — `hides()`, `locks_for_night()` — where a call is an order, not a description of one

```py
----------[ Bad ]----------

def reloads(self, price_cents: int) -> None:
    self.price_cents = price_cents
    self.printed += 1

----------[ Good ]----------

class AisleLabel:
    def __init__(self, sku: str, price_cents: int) -> None:
        self.sku = sku
        self.price_cents = price_cents
        self.printed = 0

    def reload(self, price_cents: int) -> None:
        self.price_cents = price_cents
        self.printed += 1
```
