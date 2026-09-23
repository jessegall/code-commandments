# A shelf label that is ordered to reload its price, named as though it were describing itself. Below it, the
# FIX: the same body, named as the order the caller gives.


class ShelfLabel:
    def __init__(self, sku: str, price_cents: int) -> None:
        self.sku = sku
        self.price_cents = price_cents
        self.printed = 0

    # @sin NarratedCommand
    def reloads(self, price_cents: int) -> None:
        self.price_cents = price_cents
        self.printed += 1


# @fixed NarratedCommand
class AisleLabel:
    def __init__(self, sku: str, price_cents: int) -> None:
        self.sku = sku
        self.price_cents = price_cents
        self.printed = 0

    def reload(self, price_cents: int) -> None:
        self.price_cents = price_cents
        self.printed += 1
