# An order card built for the dashboard with an empty body because this caller has none — the card's
# type says every card has a body. Below it, the FIX: a narrower card that only promises a title.
from dataclasses import dataclass


@dataclass(frozen=True)
class OrderCard:
    title: str
    body: str


def card_for(order) -> OrderCard:
    # @sin PlaceholderFilledData
    return OrderCard(title=order.number, body="")


# @fixed PlaceholderFilledData
@dataclass(frozen=True)
class OrderHeading:
    title: str


# @fixed PlaceholderFilledData
def heading_for(order) -> OrderHeading:
    return OrderHeading(title=order.number)
