# A ledger export that warms the accounting API the moment anyone builds one — so merely HAVING an
# export costs a request nobody asked for. Below it, the FIX: the client kept, the request made when
# the export is read.


# @sin ConstructorSideEffect
class LedgerExport:
    def __init__(self, client, period: str) -> None:
        self.client = client
        self.period = period
        client.warm(f"/ledger/{period}")

    def body(self) -> bytes:
        return self.client.get(f"/ledger/{self.period}")


# @fixed ConstructorSideEffect
class LazyLedgerExport:
    def __init__(self, client, period: str) -> None:
        self.client = client
        self.period = period

    def body(self) -> bytes:
        return self.client.get(f"/ledger/{self.period}")
