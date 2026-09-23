# The home screen, at the top of the stack. It imports prices from a package the stack never declared, which
# is free.
# @righteous NamespaceDependency
from ..pricing.rates import rate_for

ACCENT = "#cc3300"


def path(title: str) -> str:
    return f"/home/{title}?from={rate_for(title)}"
