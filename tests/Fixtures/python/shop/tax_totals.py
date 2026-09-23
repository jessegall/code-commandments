# A tax total whose comment narrates the assignment below it. Below it, the FIX: the comment says why.


class TaxTotal:
    def __init__(self, lines: list[int], rate: float) -> None:
        self.lines = lines
        self.rate = rate

    def amount(self) -> int:
        # set the total to the lines sum
        # @sin RestatedComment
        total = sum(self.lines)
        return round(total * self.rate)

    # @fixed RestatedComment
    def rounded_amount(self) -> int:
        # the tax office rounds each invoice once, never per line
        subtotal = sum(self.lines)
        return round(subtotal * self.rate)
