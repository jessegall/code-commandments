# Invoices that read the warehouse's picking list and its bin locations — the thicker side of the pair.
from ..warehouse import picking
from ..warehouse.bins import Bin


class Invoice:
    def __init__(self, number: str) -> None:
        self.number = number

    def lines(self) -> list[str]:
        return picking.lines(self.number) + [str(Bin())]
