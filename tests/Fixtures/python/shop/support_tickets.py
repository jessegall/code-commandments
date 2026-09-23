# A support ticket copied with changes through one generic `**changes` method. Below it, the FIX: the
# operation the desk kept spelling out, named on the ticket.


class TicketMeta:
    def __init__(self, level: int) -> None:
        self.level = level

    @classmethod
    def of(cls, level: int) -> "TicketMeta":
        return cls(level)

    def to_dict(self) -> dict:
        return {"level": self.level}


class Ticket:
    def __init__(self, subject: str, meta: dict) -> None:
        self.subject = subject
        self.meta = meta

    def evolve(self, **changes) -> "Ticket":
        return Ticket(changes.get("subject", self.subject), changes.get("meta", self.meta))


# @fixed RepeatedNamedCall
class RoutedTicket:
    def __init__(self, subject: str, meta: dict) -> None:
        self.subject = subject
        self.meta = meta

    def evolve(self, **changes) -> "RoutedTicket":
        return RoutedTicket(changes.get("subject", self.subject), changes.get("meta", self.meta))

    def escalated_to(self, level: int) -> "RoutedTicket":
        return self.evolve(meta=TicketMeta.of(level).to_dict())
