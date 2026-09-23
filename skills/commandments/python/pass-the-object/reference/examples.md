# Python pass the object — demand what you use, not an id and its container — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-computed-boolean-argument

a method taking only bools that every caller computes from the same object — the decision re-derived at each call site

```py
----------[ Bad ]----------

def allows(self, expired: bool) -> bool:
    return not expired

----------[ Good ]----------

# in return_desk.py
def allows_for(self, purchase: Purchase) -> bool:
    return purchase.days_since <= 30

# in return_desk.py
def offers_honestly(self, purchases: list[Purchase]) -> list[Purchase]:
    return [purchase for purchase in purchases if self.policy.allows_for(purchase)]
```

### python-converted-argument

a scalar parameter its callers keep filling with the same conversion — `receipt_for(str(order.id))` call after call — because it asks for the converted form instead of the value

```py
----------[ Bad ]----------

# in tip_cents.py
def ring_up(jar: TipJar, typed: str) -> None:
    jar.add(Cents.parse(typed))

# in tip_cents.py
def ring_up_all(jar: TipJar, entries: list[str]) -> None:
    for typed in entries:
        jar.add(cents=Cents.parse(typed))

# in ledger_posting.py
def post(self, entry: LedgerEntry) -> None:
    self.printed.append(ledger_line(str(entry.number), entry.amount))

# in parcel_weighing.py
def labels(readings: list[ScaleReading]) -> list[str]:
    return [weight_label(float(reading.display)) for reading in readings]

# in parcel_weighing.py
def heaviest_label(readings: list[ScaleReading]) -> str:
    return weight_label(kilos=float(max(readings, key=lambda reading: float(reading.display)).display))

# in ledger_dump.py
def dump(entries: list[LedgerEntry]) -> str:
    lines = []
    for entry in entries:
        lines.append(ledger_line(reference=str(entry.number), amount=entry.amount))
    return "\n".join(lines)

----------[ Good ]----------

# in tip_cents.py
def add_typed(self, typed: str) -> None:
    self.total += Cents.parse(typed)

# in tip_cents.py
def ring_up_honestly(jar: TipJar, typed: str) -> None:
    jar.add_typed(typed)

# in ledger_lines.py
def ledger_line_for(entry: LedgerEntry) -> str:
    return f"#{str(entry.number).zfill(6)} {entry.amount}"
```

### python-derived-argument

a call that hands over an object and a projection of it — `persist(request, request.channel_id)` — or an object in three pieces, where the function could read them itself

```py
----------[ Bad ]----------

def finish(session: KioskSession) -> str:
    return receipt(session.total, session.items(), session.paid_by_card())

----------[ Good ]----------

def receipt_of(session: KioskSession) -> str:
    return f"{session.items()} items, {session.total} ({'card' if session.paid_by_card() else 'cash'})"
```

### python-param-resolved-from-param

a function that takes a container and a key and first resolves one against the other — `def rename(workflow, node_id)` doing `workflow.graph.node(node_id)` — when it only wanted what the key names

```py
----------[ Bad ]----------

def book(seat_map: SeatMap, number: int, guest: str) -> bool:
    seat = seat_map.seats[number]
    if seat.taken:
        return False
    seat.taken, seat.guest = True, guest
    return True

----------[ Good ]----------

def book_seat(seat: Seat, guest: str) -> bool:
    if seat.taken:
        return False
    seat.taken, seat.guest = True, guest
    return True
```
