# A product teaser built with a blank subtitle for products that have none. Below it, the FIX: the
# subtitle is honestly optional. And look-alikes: a zero count and an optional field left blank.
from dataclasses import dataclass


@dataclass(frozen=True)
class Teaser:
    name: str
    subtitle: str
    views: int = 0
    badge: str | None = None


def teaser(product) -> Teaser:
    # @sin PlaceholderFilledData
    return Teaser(product.name, "")


# @fixed PlaceholderFilledData
@dataclass(frozen=True)
class ProductTeaser:
    name: str
    subtitle: str | None = None


# @fixed PlaceholderFilledData
def product_teaser(product) -> ProductTeaser:
    return ProductTeaser(product.name)


# @righteous PlaceholderFilledData
def fresh_teaser(product) -> Teaser:
    return Teaser(product.name, product.tagline, views=0, badge="")
