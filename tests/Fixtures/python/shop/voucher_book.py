"""Gift vouchers, kept by code until they are spent."""


class Voucher:
    cents: int = 0


class VoucherBook:
    def __init__(self) -> None:
        self.vouchers: dict[str, Voucher] = {}
        self.labels: dict[str, str] = {}

    def issue(self, code: str, voucher: Voucher) -> None:
        self.vouchers[code] = voucher

    # @sin DeNulledFinder
    def voucher(self, code: str) -> Voucher | None:
        # @sin NullableRegistryLookup
        return self.vouchers.get(code, None)

    def label(self, code: str) -> str:
        # @righteous NullableRegistryLookup
        return self.labels.get(code, "gift voucher")


def spend(book: VoucherBook, code: str) -> int:
    """Every caller reads the voucher through, so the book's `None` is a failure nobody wants."""
    return book.voucher(code).cents


def is_spent(book: VoucherBook, code: str) -> bool:
    return book.voucher(code).cents == 0
