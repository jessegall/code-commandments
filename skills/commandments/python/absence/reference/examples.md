# Python absence — decide "missing" where the value is born — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-blank-string-default

`x: str = ""` standing in for absence — then asked `x == ""`, `not x` or `if x:` in its own scope

```py
----------[ Bad ]----------

def book(self, parcel: str, tracking: str = "") -> str:
    if not tracking:
        tracking = self.api.issue(parcel)
    return self.api.book(parcel, tracking)

----------[ Good ]----------

def book_tracked(self, parcel: str, tracking: str | None = None) -> str:
    if tracking is None:
        tracking = self.api.issue(parcel)
    return self.api.book(parcel, tracking)
```

### python-cancelled-fallback

`(x or "") != ""` — a value defaulted to a blank only to be compared against that same blank, so absent and empty take one branch unnamed

```py
----------[ Bad ]----------

def stock_rows(rows: list, stock) -> None:
    for row in rows:
        if row.get("qty", 0) == 0:
            continue
        stock.add(row["sku"], row["qty"])

----------[ Good ]----------

def stock_counted(rows: list, stock) -> None:
    for row in rows:
        if "qty" not in row:
            raise MissingQuantity.in_row(row)
        if row["qty"] == 0:
            continue
        stock.add(row["sku"], row["qty"])
```

### python-invented-default

`f(x or "")` — an empty string, `0` or `False` invented to fill an argument when the value is missing, a stand-in the callee cannot tell from real data

```py
----------[ Bad ]----------

def send_receipt(order, mailer) -> None:
    mailer.send(order.email or "", subject=f"Receipt {order.number}")

----------[ Good ]----------

# in receipts.py
class NoEmail(LookupError):
    @classmethod
    def on(cls, order) -> "NoEmail":
        return cls(f"order {order.number} has no email to send the receipt to")

# in receipts.py
def mail_receipt(order, mailer) -> None:
    if order.email is None:
        raise NoEmail.on(order)
    mailer.send(order.email, subject=f"Receipt {order.number}")
```
