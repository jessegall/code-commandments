# Stock kept per SKU; reserving takes from what is on hand and refuses what is not there.


class OutOfStock(Exception):
    @classmethod
    def for_sku(cls, sku: str, wanted: int) -> "OutOfStock":
        return cls(f"{sku}: {wanted} wanted, not on hand")


class Inventory:
    def __init__(self, levels: dict[str, int]) -> None:
        self._levels = dict(levels)

    def on_hand(self, sku: str) -> int:
        return self._levels.get(sku, 0)

    def reserve(self, sku: str, wanted: int) -> None:
        if self.on_hand(sku) < wanted:
            raise OutOfStock.for_sku(sku, wanted)

        self._levels[sku] -= wanted
