# Python fix at the source — where a value is born, not where it hurts — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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
