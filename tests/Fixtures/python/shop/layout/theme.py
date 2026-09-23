# The layout's theme importing a screen module outright to read its colours: another layer skipped upward.
# @sin NamespaceDependency
import shop.screens.home


def colour() -> str:
    return shop.screens.home.ACCENT
