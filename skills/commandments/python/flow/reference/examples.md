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

### python-collection-mutated-while-iterated

a loop that appends to, removes from or deletes out of the very collection it walks — the iterator reads a sequence that moved under it, so an element is skipped or it raises

```py
----------[ Bad ]----------

def sweep(reservations: dict[str, int]) -> None:
    for code in reservations.keys():
        if reservations[code] == EXPIRED:
            del reservations[code]

----------[ Good ]----------

def sweep_expired(reservations: dict[str, int]) -> None:
    expired = [code for code, left in reservations.items() if left == EXPIRED]
    for code in expired:
        del reservations[code]
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

### python-unnamed-condition

`if settings.auto and not self.said and event.at > self.since:` — three parts over two objects, so the reader works out what the condition MEANS instead of reading it

```py
----------[ Bad ]----------

def dispatch(self, crate: Crate) -> None:
    if crate.packed and crate.labelled and self.bay.is_free():
        self.bay.take(crate)
        self.sent.append(crate.reference)

----------[ Good ]----------

# in bay_loading.py
def ready_for(self, bay: "LoadingBay") -> bool:
    return self.packed and self.labelled and bay.is_free()

# in bay_loading.py
def dispatch_told(self, crate: Crate) -> None:
    if crate.ready_for(self.bay):
        self.bay.take(crate)
        self.sent.append(crate.reference)
```
