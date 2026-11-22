class Base:
    def label(self) -> str:
        return "base"


class Child(Base):
    def label(self) -> str:
        return "child"


def total(rate: int) -> int:
    return rate + 1
