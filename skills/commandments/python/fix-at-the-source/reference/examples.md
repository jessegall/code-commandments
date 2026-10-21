# Python fix at the source — where a value is born, not where it hurts — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-blocked-await

`time.sleep`, `requests.get` or `subprocess.run` inside an `async def` — the thread it stops is the event loop, so every coroutine waiting on it stops too

```py
----------[ Bad ]----------

async def poll(self, supplier: str) -> str:
    for _ in range(TRIES):
        answered = await self.feed.read(supplier)
        if answered:
            return answered

        time.sleep(EVERY)

    return ""

----------[ Good ]----------

class AwaitingPoller:
    def __init__(self, feed) -> None:
        self.feed = feed

    async def poll(self, supplier: str) -> str:
        for _ in range(TRIES):
            answered = await self.feed.read(supplier)
            if answered:
                return answered

            await asyncio.sleep(EVERY)

        return ""
```

### python-constructor-side-effect

an `__init__` that tells a collaborator to act and throws the answer away — merely building the object has an effect outside it.

```py
----------[ Bad ]----------

class CardPlugin:
    def __init__(self, registry, fee: float) -> None:
        self.fee = fee
        registry.add("card", self)

    def charge(self, amount: float) -> float:
        return amount * (1 + self.fee)

----------[ Good ]----------

# in payment_plugins.py
class CardPayments:
    def __init__(self, fee: float) -> None:
        self.fee = fee

    def charge(self, amount: float) -> float:
        return amount * (1 + self.fee)

# in payment_plugins.py
def install_card_payments(registry, fee: float) -> CardPayments:
    payments = CardPayments(fee)
    registry.add("card", payments)
    return payments
```

### python-divergent-twin

two functions do the same job, but one of them skips a step the other takes — usually a fix made in one copy and forgotten in the other.

```py
----------[ Bad ]----------

# in receipt_signing.py
def signature_matches(secret: bytes, receipt: bytes, claimed: str) -> bool:
    key = hashlib.pbkdf2_hmac("sha256", secret, binascii.hexlify(receipt[:4]), 1000)
    digest = hmac.new(key, receipt, "sha512").digest()
    expected = base64.urlsafe_b64encode(digest).decode()
    return hmac.compare_digest(expected, claimed)

# in receipt_signing.py
def refund_signature_matches(secret: bytes, receipt: bytes, claimed: str) -> bool:
    key = hashlib.pbkdf2_hmac("sha256", secret, binascii.hexlify(receipt[:4]), 1000)
    digest = hmac.new(key, receipt, "sha512").digest()
    expected = base64.urlsafe_b64encode(digest).decode()
    return expected == claimed

----------[ Good ]----------

def refund_signature_checks(secret: bytes, receipt: bytes, claimed: str) -> bool:
    return signature_matches(secret, receipt, claimed)
```

### python-frozen-default

a parameter defaulted to `datetime.now()`, `uuid4()` or another call that answers differently each time — it runs once when the def is defined, so every call that omits it gets the moment the module was imported

```py
----------[ Bad ]----------

def stamp_receipt(order: str, at: datetime = datetime.now()) -> str:
    return f"{order} at {at.isoformat()}"

----------[ Good ]----------

def stamp_receipt_now(order: str, at: datetime | None = None) -> str:
    issued = at if at is not None else datetime.now()

    return f"{order} at {issued.isoformat()}"
```

### python-mutable-default

a parameter defaulted to a `[]`, `{}` or `set()` — one container, made once, shared by every call that omits it

```py
----------[ Bad ]----------

def walk(self, lines: list[str], notes: list[str] = []) -> list[str]:
    for line in lines:
        if line.startswith("damaged"):
            notes.append(line)

    return notes

----------[ Good ]----------

def walk_freshly(self, lines: list[str], notes: list[str] | None = None) -> list[str]:
    written = list(notes) if notes is not None else []
    for line in lines:
        if line.startswith("damaged"):
            written.append(line)

    return written
```

### python-mutable-static-state

a `global` written from a function, or a class attribute set from a method — state no instance owns, changed by whoever ran last

```py
----------[ Bad ]----------

@classmethod
def load(cls, rates: dict) -> None:
    cls.rates = rates

----------[ Good ]----------

class TaxRates:
    def __init__(self, rates: dict) -> None:
        self.rates = rates

    def rate_for(self, region: str) -> float:
        return self.rates[region]
```

### python-unread-collaborator

a collaborator `__init__` keeps as an attribute of its own, `self._mailer = mailer`, that nothing anywhere reads — the class asks for something it does not use

```py
----------[ Bad ]----------

def __init__(self, printer: Printer, courier: Courier) -> None:
    self._printer = printer
    self._courier = courier

----------[ Good ]----------

class PackingDesk:
    def __init__(self, printer: Printer) -> None:
        self._printer = printer

    def pack(self, order: str, lines: list[str]) -> int:
        for line in lines:
            self._printer.print_slip(f"{order}: {line}")

        return len(lines)
```
