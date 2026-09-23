# A basket dataclass with a class-level limit declared under its fields. Below it, the FIX: the ClassVar
# above the fields.
from dataclasses import dataclass, field
from typing import ClassVar


@dataclass
class Basket:
    owner: str
    lines: list = field(default_factory=list)
    # @sin MemberOutOfOrder
    LIMIT: ClassVar[int] = 25

    def full(self) -> bool:
        return len(self.lines) >= self.LIMIT


# @fixed MemberOutOfOrder
@dataclass
class Trolley:
    CAPACITY: ClassVar[int] = 25

    owner: str
    items: list = field(default_factory=list)

    def full(self) -> bool:
        return len(self.items) >= self.CAPACITY
