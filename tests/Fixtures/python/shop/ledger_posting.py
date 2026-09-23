"""Posts ledger entries and prints the line each became."""

from .ledger_lines import LedgerEntry, ledger_line, ledger_line_for


class Posting:
    def __init__(self) -> None:
        self.printed: list[str] = []

    def post(self, entry: LedgerEntry) -> None:
        # @sin ConvertedArgument
        self.printed.append(ledger_line(str(entry.number), entry.amount))

    # @fixed ConvertedArgument
    def post_honestly(self, entry: LedgerEntry) -> None:
        self.printed.append(ledger_line_for(entry))
