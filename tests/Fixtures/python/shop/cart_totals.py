# A basket summary assembled from a parsed line of text, the parts returned as a dict. Below it, the
# FIX: a named result. And look-alikes: the wire shape of one object, and HTTP headers.
from dataclasses import dataclass


def parse_line(raw: str) -> dict:
    sku, _, quantity = raw.partition("x")
    # @sin DictReturnBag
    return {"sku": sku.strip(), "quantity": int(quantity)}


# @fixed DictReturnBag
@dataclass(frozen=True)
class BasketLine:
    sku: str
    quantity: int


# @fixed DictReturnBag
def parse_basket_line(raw: str) -> BasketLine:
    sku, _, quantity = raw.partition("x")
    return BasketLine(sku=sku.strip(), quantity=int(quantity))


# @righteous DictReturnBag
class Basket:
    def __init__(self, lines: list, owner: str) -> None:
        self.lines = lines
        self.owner = owner

    def to_json(self) -> dict:
        return {"owner": self.owner, "lines": len(self.lines)}


# @righteous DictReturnBag
def download_headers(size: int, kind: str) -> dict:
    return {"Content-Length": str(size), "Content-Type": kind}
