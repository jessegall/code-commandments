# Python repeated call helper — name what you keep spelling out — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### python-repeated-guard

the same compound `and` condition recurs in 2+ places — it still counts even when reordered, or read through a local variable — and nobody has named it.

```py
----------[ Bad ]----------

# in loyalty_points.py
def redeem(member: Member, cost: int) -> int:
    if member.points >= cost and not member.frozen:
        return member.points - cost
    raise ValueError(cost)

# in loyalty_points.py
def preview(member: Member, cost: int) -> str:
    enough = member.points >= cost
    return "redeemable" if not member.frozen and enough else "locked"

# in events.py
def on_event(self, event) -> None:
    if event.kind == "order" and event.action in ("created", "updated"):
        self.handle(event)
        event.acknowledge(self.__class__.__name__)

# in events.py
def on_event(self, event) -> None:
    if event.kind == "order" and event.action in ("created", "updated"):
        self.handle(event)
        event.acknowledge(self.__class__.__name__)

# in dispatch_desk.py
def outgoing(self) -> list[Parcel]:
    return [parcel for parcel in self.parcels if parcel.labelled and parcel.weight_grams > 0]

# in dispatch_desk.py
def may_leave(self, parcel: Parcel) -> bool:
    return parcel.labelled and parcel.weight_grams > 0

# in courier_pickups.py
def load(van: list[Parcel], parcel: Parcel) -> None:
    if parcel.labelled and parcel.weight_grams > 0:
        van.append(parcel)

----------[ Good ]----------

class Account:
    def __init__(self, points: int, frozen: bool) -> None:
        self.points = points
        self.frozen = frozen

    def can_redeem(self, cost: int) -> bool:
        return self.points >= cost and not self.frozen

    def redeem(self, cost: int) -> int:
        if self.can_redeem(cost):
            return self.points - cost
        raise ValueError(cost)
```

### python-repeated-named-call

the same `**changes` call is built the same way with the same keyword at 2+ sites — an operation that has no name on the type it belongs to.

```py
----------[ Bad ]----------

# in escalations.py
def escalate(self, ticket: Ticket) -> Ticket:
    self.pager.append(ticket.subject)
    return ticket.evolve(meta=TicketMeta.of(3).to_dict())

# in cart_lines.py
def mark_gift(line: CartLine) -> CartLine:
    return line.amend(flags={"gift": True, "wrap": "paper"})

# in cart_lines.py
def mark_sample(line: CartLine) -> CartLine:
    return line.amend(flags={"sample": True})

# in support_desk.py
def raise_level(ticket: Ticket, level: int) -> Ticket:
    return ticket.evolve(meta=TicketMeta.of(level).to_dict())

----------[ Good ]----------

class RoutedTicket:
    def __init__(self, subject: str, meta: dict) -> None:
        self.subject = subject
        self.meta = meta

    def evolve(self, **changes) -> "RoutedTicket":
        return RoutedTicket(changes.get("subject", self.subject), changes.get("meta", self.meta))

    def escalated_to(self, level: int) -> "RoutedTicket":
        return self.evolve(meta=TicketMeta.of(level).to_dict())
```

### python-repeated-type-guard

the same multi-`isinstance` narrowing (`isinstance(x, A) and isinstance(x.y, B)`) is written in 2+ places — a check on a shape that nobody has named.

```py
----------[ Bad ]----------

# in refund_events.py
def refund_cents(event: object, limit_cents: int) -> int:
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        return 0
    return limit_cents

# in report_cells.py
def render(self, cell: object) -> str:
    if isinstance(cell, Cell) and isinstance(cell.content, Table):
        return f"<table rows={len(cell.content.rows)}>"
    return str(cell)

# in report_cells.py
def export(self, cells: list) -> list:
    return [cell.content.rows for cell in cells if isinstance(cell, Cell) and isinstance(cell.content, Table)]

# in payment_events.py
def challenge_url(event: object) -> str:
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        return event.step.url
    return ""

# in payment_events.py
def notify_customer(event: object, outbox: list[str]) -> None:
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        outbox.append(event.step.url)

----------[ Good ]----------

# in payment_events.py
@property
def is_challenged(self) -> bool:
    return isinstance(self.step, Challenge)

# in payment_events.py
def challenge_link(event: object) -> str:
    if isinstance(event, CardPayment) and event.is_challenged:
        return str(event.step)
    return ""
```
