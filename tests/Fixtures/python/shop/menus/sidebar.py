# The FIX for a menu that reached up into a screen: it builds on the layout, which its layer declared it may
# use, and takes what it shows as an argument.
# @fixed NamespaceDependency
from ..layout.grid import cell


def sidebar(titles: list[str]) -> str:
    return "".join(cell(title) for title in titles)
