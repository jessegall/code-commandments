# Python tell, don't ask — behaviour lives with its data — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-feature-envy

a method that loops another object's collection or writes its fields, reaching into it more than into its own state — behaviour exiled from the object it works on

```py
----------[ Bad ]----------

def vegan_line(self, board: MenuBoard) -> str:
    return ", ".join(dish.name for dish in board.dishes if dish.vegan)

----------[ Good ]----------

def vegan_names(self) -> list[str]:
    return [dish.name for dish in self.dishes if dish.vegan]
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
