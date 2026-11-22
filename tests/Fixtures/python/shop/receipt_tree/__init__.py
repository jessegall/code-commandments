from enum import Enum
from typing import Protocol
from shop.type_ports import ReceiptTreeService


# @sin LoneImplementerInterface
class ReceiptTreePort(Protocol):
    def export_receipt_tree(self, counter: str, receipts: list[str]) -> str: ...


class ReceiptTreeExporter(ReceiptTreePort):
    def export_receipt_tree(self, counter: str, receipts: list[str]) -> str:
        clean = (row.strip() for row in receipts)
        return counter + ": " + ", ".join(row for row in clean if row)


class ReceiptTreeOwner:
    def __init__(
        self,
        # @sin ConcreteCrossModuleDependency
        service: ReceiptTreeService,
        counter: str,
    ):
        self.service = service
        self.pending = set()
        self.counter = counter

    def register(self, receipt: str) -> None:
        if receipt:
            self.pending.add(receipt)


class ReceiptTreeSource:
    def __init__(self, till: str) -> None:
        self.till = till
        self.finished = []

    def read(self) -> str:
        return f"{self.till}: {len(self.finished)} receipts"

    def record(self, receipt: str) -> None:
        self.finished.append(receipt.strip())


# @sin MiddleMan
class ReceiptTreeForwarder:
    def __init__(self, source: ReceiptTreeSource):
        self.source = source

    def read(self) -> str:
        return self.source.read()

    def record(self, receipt: str) -> None:
        self.source.record(receipt)


class ReceiptTreeBroken(ReceiptTreeSource):
    # @sin RefusedBequest
    def read(self) -> str:
        raise NotImplementedError()

    def last_receipts(self, count: int) -> list[str]:
        if count <= 0:
            return []
        return self.finished[-count:]


# @sin HandRolledRegistry
class ReceiptTreeDirectory:
    def __init__(self):
        self.totals: dict[int, list[int] | None] = {}

    def close(self, drawer: int, totals: list[int]) -> None:
        self.totals[drawer] = totals.copy()

    def counted(self, drawer: int, minimum: int) -> int:
        if self.totals[drawer] is None:
            raise KeyError(drawer)
        return sum(count for count in self.totals[drawer] if count >= minimum)


class ReceiptTreeLabel:
    text: str


class ReceiptTreeBin:
    label: ReceiptTreeLabel


class ReceiptTreeShelf:
    bin: ReceiptTreeBin


def read_receipt_tree(shelves: list[ReceiptTreeShelf], width: int) -> list[str]:
    printed = []
    for shelf in shelves:
        # @sin TrainWreck
        label = shelf.bin.label.text
        if len(label) <= width:
            printed.append(label)
    return printed


class ReceiptTreeSummary:
    def __init__(self, slips: list[int], station: str):
        self.slips = slips
        self.station = station

    # @sin UnusedPrivateMember
    def _draft(self) -> str:
        heading = self.station.upper()
        numbers = " / ".join(str(number) for number in self.slips)
        return f"{heading}: {numbers}"


class ReceiptTreeStage(Enum):
    OPEN = "open"
    SEALED = "sealed"


class ReceiptTreeBatch:
    stage: ReceiptTreeStage

    def seal(self) -> None:
        self.stage = ReceiptTreeStage.SEALED


# @sin NearDuplicateFunction
def seal_receipt_tree(batch: ReceiptTreeBatch) -> None:
    # @sin GuardBesideTransition
    if batch.stage != ReceiptTreeStage.OPEN:
        # @sin MessageAtThrow
        raise ValueError("Already closed")
    batch.seal()
