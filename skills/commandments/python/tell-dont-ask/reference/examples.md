# Python tell, don't ask — behaviour lives with its data — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-feature-envy

a method that loops another object's collection or writes its fields, reaching into it more than into its own state — behaviour exiled from the object it works on

```py
----------[ Bad ]----------

def redeem(self, card: StampCard) -> None:
    card.closed = True
    card.reward = "free coffee"
    card.stamps = 0
    self.rewards_given += 1

----------[ Good ]----------

# in stamp_cards.py
def close_with(self, reward: str) -> None:
    self.closed = True
    self.reward = reward
    self.stamps = 0

# in stamp_cards.py
def redeem_card(self, card: StampCard) -> None:
    card.close_with("free coffee")
    self.rewards_given += 1
```

### python-keyed-lookup-envy

a method that uses an object's key to fetch a fact about it through a collaborator — `self.registry.get(node.key).reserved` — treating the object as a key into its own data

```py
----------[ Bad ]----------

def heading(self, aisle: Aisle) -> str:
    return self.catalogue.entry(aisle.number).heading

----------[ Good ]----------

# in aisle_signs.py
def sign_heading(self) -> str:
    return self.spec.heading

# in aisle_signs.py
def heading_of(self, aisle: Aisle) -> str:
    return aisle.sign_heading()
```

### python-type-switch

an `isinstance` ladder over classes the codebase owns — the value is asked what it is so the caller can decide what to do.

```py
----------[ Bad ]----------

def quote(parcel: Parcel) -> int:
    if isinstance(parcel, Letter):
        return 120
    elif isinstance(parcel, Pallet):
        return 4000 + parcel.weight_grams // 100
    return 500

----------[ Good ]----------

def quote_told(parcel: Parcel) -> int:
    return parcel.rate_cents()
```
