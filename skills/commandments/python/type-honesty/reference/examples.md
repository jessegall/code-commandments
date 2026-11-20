# Python type honesty — the annotation must not lie — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-constant-property

an `@property` whose body never reads `self` — `return "box"` — a stored value made to look like a computed one.

```py
----------[ Bad ]----------

@property
def kind(self) -> str:
    return "envelope"

----------[ Good ]----------

class Letter:
    kind = "envelope"

    def __init__(self, weight: float) -> None:
        self.weight = weight
```

### python-filled-parameter

a def returning `None` that writes an attribute of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed

```py
----------[ Bad ]----------

def add_lines(self, slip: TillSlip, lines: list[int]) -> None:
    slip.net = sum(lines)

----------[ Good ]----------

class TillTotals:
    def price(self, lines: list[int], tax_percent: int) -> int:
        net = sum(lines)

        return net + self.tax_on(net, tax_percent)

    def tax_on(self, net: int, tax_percent: int) -> int:
        return net * tax_percent // 100
```

### python-impossible-guard

`if order is None` where `order` is annotated as a class with no `| None` — one of the two is wrong, and both are worth knowing

```py
----------[ Bad ]----------

def has_translation(self, language: Language) -> bool:
    if language is None:
        return False

    return language.code in self.texts

----------[ Good ]----------

def has_text_in(self, language: Language) -> bool:
    return language.code in self.texts
```

### python-scratch-state-restore

`previous = self.scope … self.scope = previous` — an attribute used as per-call scratch, saved and restored around the call

```py
----------[ Bad ]----------

def visit(self, category) -> None:
    previous = self.path
    self.path = [*previous, category.name]
    try:
        self.seen.append("/".join(self.path))
        for child in category.children:
            self.visit(child)
    finally:
        self.path = previous

----------[ Good ]----------

class CategoryPaths:
    def __init__(self) -> None:
        self.seen: list = []

    def visit(self, category, path: tuple = ()) -> None:
        here = (*path, category.name)
        self.seen.append("/".join(here))
        for child in category.children:
            self.visit(child, here)
```
