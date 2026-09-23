"""Tips, entered as text at the till and kept in cents."""


class Cents:
    @classmethod
    def parse(cls, typed: str) -> int:
        return round(float(typed) * 100)


class TipJar:
    def __init__(self) -> None:
        self.total = 0

    def add(self, cents: int) -> None:
        self.total += cents

    # @fixed ConvertedArgument
    def add_typed(self, typed: str) -> None:
        self.total += Cents.parse(typed)


def ring_up(jar: TipJar, typed: str) -> None:
    # @sin ConvertedArgument
    jar.add(Cents.parse(typed))


def ring_up_all(jar: TipJar, entries: list[str]) -> None:
    for typed in entries:
        # @sin ConvertedArgument
        jar.add(cents=Cents.parse(typed))


# @fixed ConvertedArgument
def ring_up_honestly(jar: TipJar, typed: str) -> None:
    jar.add_typed(typed)
