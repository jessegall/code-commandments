# Python flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-coalesced-loop-subject

`for x in d.get(k, [])` / `for x in y or []` over a parameter — whether the caller handed anything over, decided in the loop header instead of stated as a guard

```py
----------[ Bad ]----------

def descendants(below: dict, parent: str) -> list:
    found = []
    for child in below.get(parent, []):
        found.append(child)
        found.extend(descendants(below, child))
    return found

----------[ Good ]----------

# in categories.py
def children_index(pairs) -> defaultdict:
    below = defaultdict(list)
    for parent, child in pairs:
        below[parent].append(child)
    return below

# in categories.py
def descendants_of(below: defaultdict, parent: str) -> list:
    found = []
    for child in below[parent]:
        found.append(child)
        found.extend(descendants_of(below, child))
    return found
```

### python-conditional-statement

a bare `a() if x else b()` statement — a conditional expression whose value nothing reads, so it chooses an action, not a value.

```py
----------[ Bad ]----------

def back_up(source: Path, target: Path) -> None:
    shutil.copytree(source, target) if source.is_dir() else shutil.copy2(source, target)

----------[ Good ]----------

def back_up_path(source: Path, target: Path) -> None:
    if source.is_dir():
        shutil.copytree(source, target)
        return
    shutil.copy2(source, target)
```

### deep-python-nesting

An `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops

```py
----------[ Bad ]----------

def usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer == customer.id:
            if coupon.starts <= today <= coupon.ends:
                if coupon.uses_left > 0:
                    if not coupon.revoked:
                        return coupon
    return None

----------[ Good ]----------

def first_usable_coupon(coupons, customer, today):
    for coupon in coupons:
        if coupon.customer != customer.id or coupon.revoked:
            continue
        if coupon.starts <= today <= coupon.ends and coupon.uses_left > 0:
            return coupon
    return None
```

### python-loop-wrapped-in-if

A `for` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition

```py
----------[ Bad ]----------

def invoice_all(orders, ledger) -> int:
    sent = 0
    for order in orders:
        if order.delivered:
            invoice = ledger.draft(order.number, order.total())
            ledger.send(invoice)
            sent += 1
    return sent

----------[ Good ]----------

def invoice_delivered(orders, ledger) -> int:
    sent = 0
    for order in orders:
        if not order.delivered:
            continue
        invoice = ledger.draft(order.number, order.total())
        ledger.send(invoice)
        sent += 1
    return sent
```

### python-nested-conditional

`a if x else b if y else c` — a conditional expression inside another's branch, a branching decision folded into one line

```py
----------[ Bad ]----------

def badge(order) -> str:
    return "green" if order.status == "sent" else "amber" if order.status == "draft" else "red"

----------[ Good ]----------

def badge_for(order) -> str:
    match order.status:
        case "sent":
            return "green"
        case "draft":
            return "amber"
        case _:
            return "red"
```

### redundant-python-else

An `else:` after an `if` branch that already left — it ends in `return`, `raise`, `continue` or `break` — indenting the rest of the function for nothing

```py
----------[ Bad ]----------

def price(line: Line) -> int:
    if line.promotion is not None:
        return line.promotion.apply(line.unit_price) * line.quantity
    else:
        subtotal = line.unit_price * line.quantity
        return subtotal - subtotal // 100 if line.quantity >= 100 else subtotal

----------[ Good ]----------

def price_of(line: Line) -> int:
    if line.promotion is not None:
        return line.promotion.apply(line.unit_price) * line.quantity

    subtotal = line.unit_price * line.quantity
    return subtotal - subtotal // 100 if line.quantity >= 100 else subtotal
```

### python-short-circuit-statement

a bare `a and b()` or `a or b()` statement — an `and`/`or` whose value nothing reads, so the operator is really acting as an `if`.

```py
----------[ Bad ]----------

def ensure(self, key: str) -> None:
    self.store.has(key) or self.store.warm(key)
    self.store.touch(key)

----------[ Good ]----------

def ensure_warm(self, key: str) -> None:
    if not self.store.has(key):
        self.store.warm(key)
    self.store.touch(key)
```

### python-subject-ladder

An `if`/`elif` chain of four or more rungs that each test the same subject for equality with a constant — a dispatch written as a ladder.

```py
----------[ Bad ]----------

def carrier_for(service: str) -> str:
    if service == "express":
        return "dhl"
    elif service == "standard":
        return "postnl"
    elif service == "economy":
        return "dpd"
    elif service == "freight":
        return "schenker"
    raise ValueError(service)

----------[ Good ]----------

class Service(Enum):
    EXPRESS = "express"
    STANDARD = "standard"
    ECONOMY = "economy"
    FREIGHT = "freight"

    def carrier(self) -> str:
        return {
            Service.EXPRESS: "dhl",
            Service.STANDARD: "postnl",
            Service.ECONOMY: "dpd",
            Service.FREIGHT: "schenker",
        }[self]
```
