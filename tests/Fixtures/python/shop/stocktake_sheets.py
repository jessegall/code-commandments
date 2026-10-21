# A stocktake's running counts, with the aisles that still need a second pass. The ledger it holds is read by
# nobody here: the counts are written back by the shift that closes the take. Below it, the look-alike that is
# not the sin, a ledger one method reads.


class TakeLedger:
    def write(self, sku: str, counted: int) -> None:
        pass


class StocktakeSheets:
    def __init__(self, ledger: TakeLedger, expected: dict[str, int]) -> None:
        # @sin UnreadCollaborator
        self._ledger = ledger
        self._expected = expected
        self._counted: dict[str, int] = {}

    def count(self, sku: str, counted: int) -> None:
        self._counted[sku] = counted

    def needs_a_second_pass(self) -> list[str]:
        return [sku for sku, held in self._expected.items() if self._counted.get(sku, -1) != held]


# @righteous UnreadCollaborator
class SettledSheets:
    def __init__(self, ledger: TakeLedger) -> None:
        self._settling = ledger

    def settle(self, sku: str, counted: int) -> None:
        self._settling.write(sku, counted)
