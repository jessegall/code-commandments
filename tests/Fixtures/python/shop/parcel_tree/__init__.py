from enum import Enum
from typing import Protocol
from shop.type_ports import ParcelTreeService


# @sin LoneImplementerInterface
class ParcelTreePort(Protocol):
    def send(self, carrier: str, dispatches: list[bytes]) -> int: ...
    def receive(self, token: bytes) -> bytes: ...


class ParcelTreeExporter(ParcelTreePort):
    def __init__(self, active: set[str]):
        self.active = active

    def send(self, carrier: str, dispatches: list[bytes]) -> int:
        if carrier not in self.active:
            return 0
        return sum(len(label) for label in dispatches)

    def receive(self, token: bytes) -> bytes:
        return token.rstrip(b"\x00")


class ParcelTreeOwner:
    def __init__(
        self,
        # @sin ConcreteCrossModuleDependency
        tracking: ParcelTreeService,
        event: str,
        subscribers: list[str],
    ):
        self.tracking = tracking
        self.pending = [f"{event}/{name}" for name in subscribers if name]

    def departures(self) -> list[str]:
        suffix = self.tracking.read()
        return [ticket + "/" + suffix for ticket in self.pending]


class ParcelTreeSource:
    def __init__(self, sender, from_country: str):
        self.sender = sender
        self.country = from_country

    def read(self) -> str:
        return self.country

    def dispatch(self, weight: int, appointment: str) -> float:
        return self.sender(self.country, weight, appointment)

    def cancel_shipment(self, appointment: str) -> None:
        self.sender(self.country, 0, appointment)


# @sin MiddleMan
class ParcelTreeForwarder:
    def __init__(self, carrier: ParcelTreeSource):
        self.carrier = carrier

    def dispatch(self, grams: int, when: str) -> float:
        return self.carrier.dispatch(grams, when)

    def cancel_shipment(self, late: str) -> None:
        self.carrier.cancel_shipment(late)


class ParcelTreeBroken(ParcelTreeSource):
    # @sin RefusedBequest
    def read(self) -> str:
        raise NotImplementedError()

    def collection_tags(self, label: str, bays: dict[int, list[str]]) -> dict[int, int]:
        matches = {}
        for dock, queued in bays.items():
            count = queued.count(label)
            if count > 0:
                matches[dock] = count
        return matches


# @sin HandRolledRegistry
class ParcelTreeDirectory:
    def __init__(self, manifest: str):
        self.arrival: dict[str, int] = {}
        self.manifest = manifest

    def book(self, shipment: str, minute: int) -> None:
        self.arrival[shipment] = minute

    def notify(self, shipment: str, send) -> None:
        if shipment not in self.arrival:
            raise KeyError(shipment)
        send(self.manifest, shipment, self.arrival[shipment])


class ParcelTreeLabel:
    text: str


class ParcelTreeBin:
    label: ParcelTreeLabel


class ParcelTreeShelf:
    bin: ParcelTreeBin


def read_parcel_tree(
    shelf: ParcelTreeShelf, region: str, codes: dict[str, int], send,
) -> None:
    # @sin TrainWreck
    label = shelf.bin.label.text
    cost = codes.get(region + "/" + label, 0)
    send((region, label, cost))


class ParcelTreeSummary:
    def __init__(self, routes: dict[str, list[bytes]], depart: int):
        self.routes = routes
        self.depart = depart

    # @sin UnusedPrivateMember
    def _preflight(self, gate: str) -> bytes:
        marker = bytes([self.depart])
        return marker + b"|".join(self.routes.get(gate, []))


class ParcelTreeStage(Enum):
    OPEN = "open"
    SEALED = "sealed"


class ParcelTreeBatch:
    stage: ParcelTreeStage

    def seal(self) -> None:
        self.stage = ParcelTreeStage.SEALED


def seal_parcel_tree(batches: dict[int, ParcelTreeBatch], receipt) -> int:
    sealed = 0
    for dock, active in batches.items():
        if dock < 1:
            continue
        # @sin GuardBesideTransition
        if ParcelTreeStage.SEALED == active.stage:
            # @sin MessageAtThrow
            raise ValueError("This bay has already sealed its parcels")
        active.seal()
        receipt(dock)
        sealed += 1
    return sealed
