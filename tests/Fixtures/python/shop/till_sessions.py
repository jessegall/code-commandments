"""A cashier's session on a till, opened at the start of a shift."""


class Cashier:
    name: str = ""


class TillSession:
    def __init__(self) -> None:
        self.cashier: Cashier | None = None

    def open(self, cashier: Cashier) -> None:
        self.cashier = cashier

    def receipt_footer(self) -> str:
        # @sin MaskedInvariant
        return "Served by " + getattr(self.cashier, "name", "the till")
