from enum import Enum
from typing import Protocol
from shop.type_ports import VoucherTreeService


# @sin LoneImplementerInterface
class VoucherTreePort(Protocol):
    def issue(self, face_cents: int) -> str: ...
    def void(self, voucher: str) -> bool: ...
    def held(self) -> list[str]: ...


class VoucherTreeExporter(VoucherTreePort):
    def __init__(self, prefix: str):
        self.serial = 0
        self.prefix = prefix
        self.live: set[str] = set()

    def issue(self, face_cents: int) -> str:
        self.serial += 1
        code = f"{self.prefix}-{self.serial}-{face_cents}"
        self.live.add(code)
        return code

    def void(self, voucher: str) -> bool:
        if voucher not in self.live:
            return False
        self.live.remove(voucher)
        return True

    def held(self) -> list[str]:
        return sorted(self.live)


class VoucherTreeOwner:
    def __init__(
        self,
        location: str,
        # @sin ConcreteCrossModuleDependency
        service: VoucherTreeService,
        maximum: int,
    ):
        self.location = location.strip().upper()
        self.service = service
        self.limit = max(0, maximum)
        self.spent = 0

    def within_budget(self, credit: int) -> bool:
        return self.spent + credit <= self.limit


class VoucherTreeSource:
    def __init__(self, issued: dict[int, int]):
        self.issued = issued

    def read(self) -> str:
        return str(sum(self.issued.values()))

    def take(self, serial: int, amount: int) -> None:
        self.issued[serial] = amount

    def unmatched(self, expected: set[int]) -> set[int]:
        return expected.difference(self.issued)


# @sin MiddleMan
class VoucherTreeForwarder:
    def __init__(self, redemption: VoucherTreeSource):
        self.redemption = redemption

    def read(self) -> str:
        return self.redemption.read()

    def take(self, code: int, cents: int) -> None:
        self.redemption.take(code, cents)

    def unmatched(self, expected: set[int]) -> set[int]:
        return self.redemption.unmatched(expected)


class VoucherTreeBroken(VoucherTreeSource):
    # @sin RefusedBequest
    def read(self) -> str:
        raise NotImplementedError()

    def cancel(self, cancelled: list[int], audit: list[int]) -> int:
        count = 0
        for serial in cancelled:
            if serial not in self.issued:
                continue
            self.issued.pop(serial)
            audit.append(serial)
            count += 1
        return count


# @sin HandRolledRegistry
class VoucherTreeDirectory:
    def __init__(self, currency: str):
        self.credit: dict[str, int] = {}
        self.currency = currency

    def issue(self, voucher: str, earned: int) -> None:
        self.credit[voucher] = earned

    def redeem(self, voucher: str, entries: list[tuple[str, int]]) -> None:
        if self.credit[voucher] < 1:
            raise KeyError(voucher)
        entries.append((self.currency, self.credit.pop(voucher)))


class VoucherTreeLabel:
    text: str


class VoucherTreeBin:
    label: VoucherTreeLabel


class VoucherTreeShelf:
    bin: VoucherTreeBin


def read_voucher_tree(shelf: VoucherTreeShelf, scanned: dict[str, int]) -> int:
    # @sin TrainWreck
    code = shelf.bin.label.text
    return scanned.get(code, 0)


class VoucherTreeSummary:
    def __init__(self, redeemed: dict[str, int]):
        self.redeemed = redeemed

    # @sin UnusedPrivateMember
    def _largest(self) -> tuple[str, int]:
        if not self.redeemed:
            return "", 0
        serial = max(self.redeemed, key=self.redeemed.__getitem__)
        return serial, self.redeemed[serial]


class VoucherTreeStage(Enum):
    OPEN = "open"
    SEALED = "sealed"


class VoucherTreeBatch:
    stage: VoucherTreeStage

    def seal(self) -> None:
        self.stage = VoucherTreeStage.SEALED


def seal_voucher_tree(batch: VoucherTreeBatch) -> None:
    # @sin GuardBesideTransition
    if batch.stage != VoucherTreeStage.OPEN:
        # @sin MessageAtThrow
        raise ValueError("Already closed")
    batch.seal()
