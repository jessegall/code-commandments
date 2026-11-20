class InventoryLedger:
    def __init__(self, booked: dict[int, int]):
        self.booked = booked

    def available(self, number: int, reserves: int) -> int:
        return self.booked.get(number, 0) - reserves

    def update(self, changes: dict[int, int]) -> dict[int, int]:
        for number, count in changes.items():
            self.booked[number] = self.booked.get(number, 0) + count
        return self.booked.copy()

    def paired(self, first: int, last: int) -> tuple[int, int]:
        return self.booked[first], self.booked[last]


# @sin CapabilityProbeHub
class InventoryProbe:
    def __init__(self, ledger: object, reserve: int):
        self.ledger = ledger
        self.reserve = reserve

    def waitlist(self, bin_number: int) -> int:
        if isinstance(self.ledger, InventoryLedger):
            return self.ledger.available(bin_number, self.reserve)
        return 0

    def transfer(self, changes: dict[int, int]) -> dict[int, int]:
        if isinstance(self.ledger, InventoryLedger):
            return self.ledger.update(changes)
        return {}

    def report_pair(self, from_bin: int, to_bin: int) -> tuple[int, int] | None:
        if isinstance(self.ledger, InventoryLedger):
            return self.ledger.paired(from_bin, to_bin)
        return None
