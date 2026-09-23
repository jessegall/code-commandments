"""The badge a product wears on the shelf page."""

from dataclasses import dataclass


@dataclass(frozen=True)
class ShelfProduct:
    stock: int
    retired: bool


class StockBadge:
    # @sin ComputedBooleanArgument
    def colour(self, low: bool, discontinued: bool) -> str:
        if discontinued:
            return "grey"
        if low:
            return "amber"
        return "green"

    # @fixed ComputedBooleanArgument
    def colour_of(self, product: ShelfProduct) -> str:
        if product.retired:
            return "grey"
        if product.stock < 5:
            return "amber"
        return "green"


def shelf_badge(product: ShelfProduct, badge: StockBadge) -> str:
    return badge.colour(product.stock < 5, product.retired)


def search_badge(product: ShelfProduct, badge: StockBadge) -> str:
    return badge.colour(low=product.stock < 5, discontinued=product.retired)


# @fixed ComputedBooleanArgument
def listing_badge(product: ShelfProduct, badge: StockBadge) -> str:
    return badge.colour_of(product)
