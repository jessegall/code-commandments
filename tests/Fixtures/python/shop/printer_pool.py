"""The receipt printers at each till, found by the till's name."""


class ReceiptPrinter:
    def print_line(self, text: str) -> None:
        pass


class UnknownTill(LookupError):
    @classmethod
    def for_name(cls, till: str) -> "UnknownTill":
        return cls(f"no printer registered for till {till!r}")


class PrinterRegistry:
    def __init__(self) -> None:
        self._printers: dict[str, ReceiptPrinter] = {}

    def register(self, till: str, printer: ReceiptPrinter) -> None:
        self._printers[till] = printer

    def get(self, till: str) -> ReceiptPrinter | None:
        # @sin NullableRegistryLookup
        return self._printers.get(till)


class HonestPrinterRegistry:
    def __init__(self) -> None:
        self._printers: dict[str, ReceiptPrinter] = {}

    # @fixed NullableRegistryLookup
    def get(self, till: str) -> ReceiptPrinter:
        try:
            return self._printers[till]
        except KeyError as missing:
            raise UnknownTill.for_name(till) from missing

    # @fixed NullableRegistryLookup
    def __contains__(self, till: str) -> bool:
        return till in self._printers
