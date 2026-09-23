"""Dumps the ledger as text, one line per entry."""

from .ledger_lines import LedgerEntry, ledger_line


def dump(entries: list[LedgerEntry]) -> str:
    lines = []
    for entry in entries:
        # @sin ConvertedArgument
        lines.append(ledger_line(reference=str(entry.number), amount=entry.amount))
    return "\n".join(lines)
