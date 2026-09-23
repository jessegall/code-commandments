# The support desk raises a ticket's level by rebuilding its metadata at the call.
from support_tickets import Ticket, TicketMeta


def raise_level(ticket: Ticket, level: int) -> Ticket:
    # @sin RepeatedNamedCall
    return ticket.evolve(meta=TicketMeta.of(level).to_dict())
