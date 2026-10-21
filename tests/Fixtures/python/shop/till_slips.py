# A till slip priced in three steps, each one writing its answer onto the slip it was handed and handing nothing
# back. The caller reads all three figures off a slip whose signatures never said they would be there. Below it,
# the FIX: each step returns what it worked out and the caller puts it where it belongs. Writing a field of the
# slip is feature envy as well, and the fixes differ: these owe their caller a return, while `seal` owes the
# slip a `seal()` of its own.


class TillSlip:
    def __init__(self) -> None:
        self.net = 0
        self.tax = 0
        self.sealed = False


class TillPricer:
    def price(self, lines: list[int], tax_percent: int) -> int:
        slip = TillSlip()
        self.add_lines(slip, lines)
        self.add_tax(slip, tax_percent)

        return slip.net + slip.tax

    # @sin FilledParameter
    # @sin FeatureEnvy
    def add_lines(self, slip: TillSlip, lines: list[int]) -> None:
        slip.net = sum(lines)

    # @sin FilledParameter
    # @sin FeatureEnvy
    def add_tax(self, slip: TillSlip, tax_percent: int) -> None:
        slip.tax = slip.net * tax_percent // 100

    # @righteous FilledParameter
    # @sin FeatureEnvy
    def seal(self, slip: TillSlip) -> None:
        slip.sealed = True


# @fixed FilledParameter
class TillTotals:
    def price(self, lines: list[int], tax_percent: int) -> int:
        net = sum(lines)

        return net + self.tax_on(net, tax_percent)

    def tax_on(self, net: int, tax_percent: int) -> int:
        return net * tax_percent // 100
