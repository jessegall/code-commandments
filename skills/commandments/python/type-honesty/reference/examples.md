# Python type honesty — the annotation must not lie — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-constant-property

an `@property` whose body never reads `self` — `return "box"` — a stored value made to look like a computed one.

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

### python-masked-invariant

a literal answering for the object's own scratch state — `self.period.includes(day) if self.period else False` — where the field is only unset because an operation sets it part-way

```py
----------[ Bad ]----------

def reduced(self, sku: str) -> bool:
    return self.markdown.applies_to(sku) if self.markdown else False

----------[ Good ]----------

def run(self, markdown: Markdown, skus: list[str]) -> list[str]:
    return [sku for sku in skus if markdown.applies_to(sku)]
```

### python-phantom-nullable

a field annotated `X | None` that every read assumes is there and none guards — a `None` the design never has

```py
----------[ Bad ]----------

@dataclass(frozen=True)
class PriceTag:
    cents: int
    promotion: str | None = None

    def banner(self) -> str:
        return self.promotion.upper()

----------[ Good ]----------

@dataclass(frozen=True)
class PromotedTag:
    cents: int
    promotion: str

    def banner(self) -> str:
        return self.promotion.upper()
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
