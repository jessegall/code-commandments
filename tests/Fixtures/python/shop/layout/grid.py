# The layout's grid reaching up into the widgets for a card: the lowest layer depending on one above it.
# @sin NamespaceDependency
from ..widgets.card import Card


def cell(title: str) -> str:
    return f"<td>{Card(title)}</td>"
