# @example NamespaceDependency good
# The layout's row takes what it shows as an argument, so the bottom layer imports nothing above it: the
# widget layer renders the card and hands it down.
# @fixed NamespaceDependency
from html import escape


def row(content: str) -> str:
    return f"<tr>{escape(content)}</tr>"
