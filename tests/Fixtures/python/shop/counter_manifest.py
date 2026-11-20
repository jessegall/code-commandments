from dataclasses import dataclass
from enum import Enum
import json


class DeskStatus(Enum):
    OPEN = "open"
    CLOSED = "closed"


# @righteous InArrayMirrorsEnum
def takes_queue(status: DeskStatus) -> bool:
    return status is DeskStatus.OPEN


@dataclass
class CounterTicket:
    title: str
    sequence: int


class ManifestUnreadable(ValueError):
    @classmethod
    def missing_ticket(cls) -> "ManifestUnreadable":
        return cls("The manifest has no current ticket.")


# @righteous RawDecodedArrayReturn
def latest_ticket(payload: str) -> CounterTicket:
    title, sequence = json.loads(payload)
    if not title:
        # @righteous MessageAtThrow
        raise ManifestUnreadable.missing_ticket()
    return CounterTicket(title, sequence)


# @righteous ConditionalArraySpread
def picked_up(ticket: CounterTicket, show_title: bool) -> list:
    parts = [ticket.sequence]
    if show_title:
        parts.append(ticket.title)
    return parts


class DeskPrinter:
    # @righteous CapabilityProbeHub
    def __init__(self, current: CounterTicket) -> None:
        self.current = current

    # @righteous GuardBesideTransition
    def collect(self, ready: bool) -> CounterTicket:
        if not ready:
            raise ManifestUnreadable.missing_ticket()
        return self.current

    # @righteous ManufacturedFakeFill
    def assign_title(self, title: str) -> None:
        self.current.title = title
