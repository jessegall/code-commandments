# Python value objects — give related data a type — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-coupled-fields

a class whose own fields always travel together — assembled into one value again and again, guarded together, or one copying a sibling field's value — one concept held as several fields.

```py
----------[ Bad ]----------

class Weekday:
    def __init__(self, name: str, opens: int, closes: int) -> None:
        self.name = name
        self.opens = opens
        self.closes = closes

    def hours(self) -> Hours:
        return Hours(self.opens, self.closes)

    def label(self) -> str:
        opens, closes = (self.opens, self.closes)
        return f"{self.name}: {opens}-{closes}"

----------[ Good ]----------

class OpenWeekday:
    def __init__(self, name: str, hours: Hours) -> None:
        self.name = name
        self.hours = hours

    def label(self) -> str:
        return f"{self.name}: {self.hours.opens}-{self.hours.closes}"
```

### python-data-clump

The same three or more scalar parameters (`street: str, city: str, postcode: str`) threaded through functions in two or more classes or modules — one concept with no type of its own.

```py
----------[ Bad ]----------

# in delivery.py
def book_delivery(street: str, city: str, postcode: str, carrier) -> str:
    return carrier.book(f"{street}, {postcode} {city}")

# in quotes.py
def quote(postcode: str, street: str, city: str, weight_kg) -> int:
    return 495 if postcode.startswith("1") else 695

# in customers.py
def subscribe(self, name: str, email: str, phone: str, opt_in: bool) -> None:
    self.list.add(email, name)

# in customers.py
def enrol(self, phone: str, name: str, email: str, opt_in: bool = False) -> None:
    self.members.add(name, email, phone, opt_in)

----------[ Good ]----------

# in delivery.py
@dataclass(frozen=True)
class Address:
    street: str
    city: str
    postcode: str

    def line(self) -> str:
        return f"{self.street}, {self.postcode} {self.city}"

# in delivery.py
def schedule_delivery(address: Address, carrier) -> str:
    return carrier.book(address.line())
```

### python-dict-bag

A parameter typed as a dict read by string keys — `row["sku"]`, `row.get("quantity")` — a record nobody declared

```py
----------[ Bad ]----------

def label(parcel: dict[str, Any]) -> str:
    return f"{parcel['recipient']}\n{parcel['street']}\n{parcel['postcode']} {parcel['city']}"

----------[ Good ]----------

@dataclass(frozen=True)
class Parcel:
    recipient: str
    street: str
    postcode: str
    city: str

    @classmethod
    def from_payload(cls, payload: dict[str, Any]) -> "Parcel":
        return cls(payload["recipient"], payload["street"], payload["postcode"], payload["city"])

    def label(self) -> str:
        return f"{self.recipient}\n{self.street}\n{self.postcode} {self.city}"
```

### python-dict-return-bag

`return {"total": …, "tax": …}` — a record of several fields handed back as a dict its callers read by string key

```py
----------[ Bad ]----------

def progress(self, done: int, failed: int) -> dict:
    return {"done": done, "failed": failed, "left": len(self.rows) - done - failed}

----------[ Good ]----------

@dataclass(frozen=True)
class ImportProgress:
    done: int
    failed: int
    total: int

    @property
    def left(self) -> int:
        return self.total - self.done - self.failed
```

### python-hand-rolled-replace

`return Order(self.number, self.lines, self.note, "paid")` in a dataclass — every field re-listed to change one

```py
----------[ Bad ]----------

def rerated(self, factor: float) -> "PriceRule":
    return type(self)(self.sku, min(self.rate * factor, 0.9), self.starts, self.ends)

----------[ Good ]----------

def extended(self, ends: str) -> "PriceRule":
    return replace(self, ends=ends)
```

### python-mutable-value-object

a dataclass whose own methods write the fields it was built from after construction — a value that changes under everyone holding it

```py
----------[ Bad ]----------

@dataclass
class DeliveryAddress:
    street: str
    city: str
    postcode: str

    def envelope(self) -> list:
        return [self.street, f"{self.postcode}  {self.city.upper()}"]

    def in_city(self, city: str) -> bool:
        return self.city.casefold() == city.casefold()

    def move(self, street: str, city: str) -> None:
        self.street = street
        self.city = city

----------[ Good ]----------

@dataclass(frozen=True)
class ShippingAddress:
    street: str
    city: str
    postcode: str

    def moved(self, street: str, city: str) -> "ShippingAddress":
        return replace(self, street=street, city=city)
```

### python-positional-tuple-return

`return net, vat, currency` — a bundle of different things the caller must unpack by position, where a reordering breaks silently

```py
----------[ Bad ]----------

def parse(self, code: str):
    prefix, _, serial = code.partition("-")
    return (prefix, serial, self.catalog.sku_for(prefix))

----------[ Good ]----------

# in barcode_parsing.py
class Scan(NamedTuple):
    prefix: str
    serial: str
    sku: str

# in barcode_parsing.py
class NamedScanner:
    def __init__(self, catalog) -> None:
        self.catalog = catalog

    def parse(self, code: str) -> Scan:
        prefix, _, serial = code.partition("-")
        return Scan(prefix, serial, self.catalog.sku_for(prefix))
```

### python-raw-decoded-return

`return json.loads(…)` — decoded text from outside handed on as bare dicts and lists, its shape known to no type

```py
----------[ Bad ]----------

def fetch_prices(client, supplier: str):
    return json.loads(client.get(f"/suppliers/{supplier}/prices"))

----------[ Good ]----------

# in supplier_feed.py
@dataclass(frozen=True)
class SupplierPrice:
    sku: str
    cents: int

    @classmethod
    def from_json(cls, raw: dict) -> "SupplierPrice":
        return cls(sku=raw["sku"], cents=int(raw["cents"]))

# in supplier_feed.py
def fetch_supplier_prices(client, supplier: str) -> list:
    return [SupplierPrice.from_json(raw) for raw in json.loads(client.get(f"/suppliers/{supplier}/prices"))]
```
