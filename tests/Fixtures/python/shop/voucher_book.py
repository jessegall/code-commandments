"""Gift vouchers, kept by code until they are spent."""


class Voucher:
    cents: int = 0


class VoucherBook:
    def __init__(self) -> None:
        self.vouchers: dict[str, Voucher] = {}
        self.labels: dict[str, str] = {}

    def issue(self, code: str, voucher: Voucher) -> None:
        self.vouchers[code] = voucher

    def voucher(self, code: str) -> Voucher | None:
        # @sin NullableRegistryLookup
        return self.vouchers.get(code, None)

    def label(self, code: str) -> str:
        # @righteous NullableRegistryLookup
        return self.labels.get(code, "gift voucher")
