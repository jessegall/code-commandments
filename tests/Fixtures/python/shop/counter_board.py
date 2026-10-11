# The look-alike the rule is cut back for: a tuple of registered names, beside its registry, walked to
# act on each. It is not a trait of the counter — it is this one board's choice of which three to show,
# and nothing in the syntax tells that from a trait, so a collection that is only walked is left alone.
from dataclasses import dataclass


@dataclass(frozen=True)
class Counter:
    name: str
    title: str


COUNTERS = (
    Counter("bakery", "Bakery"),
    Counter("deli", "Deli"),
    Counter("fish", "Fish"),
    Counter("flowers", "Flowers"),
)

# @righteous SideTableTrait
SHOWN_ON_THE_BOARD = ("bakery", "deli", "fish")


def board(rows) -> list:
    shown = []
    for name in SHOWN_ON_THE_BOARD:
        shown.append(rows.of(name))
    return shown
