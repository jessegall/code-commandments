# An escalation policy that pages the on-call engineer and bumps the ticket — the desk's call again.
from support_tickets import Ticket, TicketMeta


class EscalationPolicy:
    def __init__(self, pager: list[str]) -> None:
        self.pager = pager

    def escalate(self, ticket: Ticket) -> Ticket:
        self.pager.append(ticket.subject)
        # @sin RepeatedNamedCall
        return ticket.evolve(meta=TicketMeta.of(3).to_dict())
