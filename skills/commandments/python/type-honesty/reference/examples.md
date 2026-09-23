# Python type honesty — the annotation must not lie — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-constant-property

an `@property` whose body never reads `self` — `return "box"` — a stored value dressed as a computed one

```py
----------[ Bad ]----------

@property
def lifetime_seconds(self) -> int:
    """How long a session stays valid."""
    return 60 * 60 * 8

----------[ Good ]----------

class ShopSession:
    LIFETIME_SECONDS: ClassVar[int] = 60 * 60 * 8

    def __init__(self, user: str) -> None:
        self.user = user

    def expires_after(self, started: int) -> int:
        return started + self.LIFETIME_SECONDS
```

### python-phantom-nullable

a field annotated `X | None` that every read assumes is there and none guards — a `None` the design never has

```py
----------[ Bad ]----------

promotion: str | None = None

----------[ Good ]----------

promotion: str
```

### python-placeholder-filled-data

`Card(title=…, body="")` — a dataclass field required as `str` handed the blank to satisfy the signature, a value the type cannot catch

```py
----------[ Bad ]----------

def teaser(product) -> Teaser:
    return Teaser(product.name, "")

----------[ Good ]----------

# in product_teasers.py
@dataclass(frozen=True)
class ProductTeaser:
    name: str
    subtitle: str | None = None

# in product_teasers.py
def product_teaser(product) -> ProductTeaser:
    return ProductTeaser(product.name)
```

### python-scratch-state-restore

`previous = self.scope … self.scope = previous` — an attribute used as per-call scratch, saved and restored around the call

```py
----------[ Bad ]----------

def bundle(self, name: str, items: list) -> None:
    self.line(name)
    depth = self.indent
    self.indent = depth + 2
    for item in items:
        self.line(item)
    self.indent = depth

----------[ Good ]----------

class IndentedPrinter:
    def __init__(self, out) -> None:
        self.out = out

    def line(self, text: str, indent: int = 0) -> None:
        self.out.append(" " * indent + text)

    def bundle(self, name: str, items: list) -> None:
        self.line(name)
        for item in items:
            self.line(item, indent=2)
```
