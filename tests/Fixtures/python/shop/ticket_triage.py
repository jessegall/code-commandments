# A support ticket escalated unless its priority is one of two raw strings the priority enum already
# holds. Below it, the FIX: the enum says which priorities wait.
from enum import StrEnum


class Priority(StrEnum):
    LOW = "low"
    NORMAL = "normal"
    URGENT = "urgent"

    @property
    def can_wait(self) -> bool:
        return self is not Priority.URGENT


class Triage:
    def __init__(self, oncall) -> None:
        self.oncall = oncall

    def route(self, ticket) -> None:
        # @sin InLiteralsMirrorsEnum
        if ticket.priority not in ["low", "normal"]:
            self.oncall.page(ticket.number)

    # @fixed InLiteralsMirrorsEnum
    def route_by_priority(self, ticket) -> None:
        if not Priority(ticket.priority).can_wait:
            self.oncall.page(ticket.number)
