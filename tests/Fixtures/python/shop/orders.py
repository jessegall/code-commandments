# The Python shop the Python detectors are proven against. It is parsed, never run: a `# @sin Name`
# comment above a declaration says the rule for that sin must flag it, and every unmarked line is a
# place it must not.

from dataclasses import dataclass


@dataclass(frozen=True)
class Line:
    sku: str
    quantity: int
    unit_price: int

    def total(self) -> int:
        return self.quantity * self.unit_price


@dataclass(frozen=True)
class Order:
    number: str
    lines: tuple[Line, ...]

    def total(self) -> int:
        return sum(line.total() for line in self.lines)

    def is_empty(self) -> bool:
        return not self.lines
