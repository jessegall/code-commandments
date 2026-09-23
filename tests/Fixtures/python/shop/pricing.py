# The price a line is sold at: a line on promotion answers first, and everything after it is the
# ordinary price — yet it is written as the other half of a choice. Below it, the FIX.


class Line:
    def __init__(self, unit_price: int, quantity: int, promotion) -> None:
        self.unit_price = unit_price
        self.quantity = quantity
        self.promotion = promotion


def price(line: Line) -> int:
    # @sin RedundantElse
    if line.promotion is not None:
        return line.promotion.apply(line.unit_price) * line.quantity
    else:
        subtotal = line.unit_price * line.quantity
        return subtotal - subtotal // 100 if line.quantity >= 100 else subtotal


# @fixed RedundantElse
def price_of(line: Line) -> int:
    if line.promotion is not None:
        return line.promotion.apply(line.unit_price) * line.quantity

    subtotal = line.unit_price * line.quantity
    return subtotal - subtotal // 100 if line.quantity >= 100 else subtotal
