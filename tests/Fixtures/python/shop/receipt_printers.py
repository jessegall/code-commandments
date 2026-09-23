# A receipt printer that opens the device it keeps the moment it is built, through the field it was
# stored in. Below it, the FIX: opened when the first receipt is printed. And look-alikes: asking a
# collaborator for something and keeping it, and a class calling its own helper.


# @sin ConstructorSideEffect
class ReceiptPrinter:
    def __init__(self, device) -> None:
        self.device = device
        self.device.open()

    def print(self, lines: list) -> None:
        self.device.write("\n".join(lines))


# @fixed ConstructorSideEffect
class DeferredReceiptPrinter:
    def __init__(self, device) -> None:
        self.device = device

    def print(self, lines: list) -> None:
        self.device.open()
        self.device.write("\n".join(lines))


# @righteous ConstructorSideEffect
class PriceBoard:
    def __init__(self, catalog) -> None:
        self.prices = catalog.prices()
        self._sort()

    def _sort(self) -> None:
        self.prices = dict(sorted(self.prices.items()))
