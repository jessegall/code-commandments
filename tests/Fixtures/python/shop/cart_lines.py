# A cart line amended with a flags dict written out at every promotion, in two places.


class CartLine:
    def __init__(self, sku: str, flags: dict) -> None:
        self.sku = sku
        self.flags = flags

    def amend(self, **changes) -> "CartLine":
        return CartLine(self.sku, changes.get("flags", self.flags))


def mark_gift(line: CartLine) -> CartLine:
    # @sin RepeatedNamedCall
    return line.amend(flags={"gift": True, "wrap": "paper"})


def mark_sample(line: CartLine) -> CartLine:
    # @sin RepeatedNamedCall
    return line.amend(flags={"sample": True})


# @righteous RepeatedNamedCall
def rename(line: CartLine, sku: str) -> CartLine:
    return line.amend(sku=sku)
