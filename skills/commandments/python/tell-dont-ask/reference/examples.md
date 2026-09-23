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

### python-type-switch

an `isinstance` ladder over classes the codebase owns — the value asked what it IS so the caller can decide what to do

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
