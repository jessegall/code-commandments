# Python class layout — the inventory at the top — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-member-after-method

a constant, class attribute or field declared below a method — the class's state hidden among its behaviour

```py
----------[ Bad ]----------

RETRIES = 3

----------[ Good ]----------

class PatientSupplierClient:
    RETRIES = 3

    def __init__(self, http) -> None:
        self.http = http

    def fetch(self, path: str) -> bytes:
        responses = (self.http.get(path) for _ in range(self.RETRIES))
        return next((response.body for response in responses if response.ok), b"")
```

### python-member-out-of-order

a constant declared below a field in the head of a class — the inventory read in an ad-hoc order

```py
----------[ Bad ]----------

LIMIT: ClassVar[int] = 25

----------[ Good ]----------

@dataclass
class Trolley:
    CAPACITY: ClassVar[int] = 25

    owner: str
    items: list = field(default_factory=list)

    def full(self) -> bool:
        return len(self.items) >= self.CAPACITY
```
