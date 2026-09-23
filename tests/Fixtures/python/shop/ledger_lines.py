"""A ledger line, printed from a posting and from an export."""

from dataclasses import dataclass


@dataclass(frozen=True)
class LedgerEntry:
    number: int
    amount: int


def ledger_line(reference: str, amount: int) -> str:
    return f"#{reference.zfill(6)} {amount}"


# @fixed ConvertedArgument
def ledger_line_for(entry: LedgerEntry) -> str:
    return f"#{str(entry.number).zfill(6)} {entry.amount}"
