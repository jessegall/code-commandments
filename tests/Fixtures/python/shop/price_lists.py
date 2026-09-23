# A price looked up in a supplier's list, a missing SKU reported as the shop's error — but only after
# a retry, deep in the handler, still without the cause. Below it, the FIX: the cause named.


class UnknownSku(Exception):
    @classmethod
    def at(cls, supplier: str, sku: str) -> "UnknownSku":
        return cls(f"{supplier} does not list {sku}")


class PriceList:
    def __init__(self, supplier: str, prices: dict) -> None:
        self.supplier = supplier
        self.prices = prices

    def price(self, sku: str, refresh) -> int:
        try:
            return self.prices[sku]
        except KeyError as missing:
            self.prices = refresh(self.supplier)
            if sku not in self.prices:
                # @sin RaiseWithoutCause
                raise UnknownSku.at(self.supplier, sku)
            return self.prices[sku]

    # @fixed RaiseWithoutCause
    def price_of(self, sku: str, refresh) -> int:
        try:
            return self.prices[sku]
        except KeyError as missing:
            self.prices = refresh(self.supplier)
            if sku not in self.prices:
                raise UnknownSku.at(self.supplier, sku) from missing
            return self.prices[sku]
