# Python value objects — give related data a type — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-data-clump

The same three or more scalar parameters (`street: str, city: str, postcode: str`) threaded through functions in two or more classes or modules — one concept wearing no name

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
