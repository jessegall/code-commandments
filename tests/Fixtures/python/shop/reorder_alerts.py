# A reorder alert whose docstring lists, after its summary, everything the class is responsible for.


# @sin BloatedDocblock
class ReorderAlert:
    """Warns the buyer when a product runs low.

    - reads the warehouse level every hour
    - emails the buyer
    - pauses the product in the storefront
    """

    def __init__(self, sku: str, threshold: int) -> None:
        self.sku = sku
        self.threshold = threshold

    def is_due(self, level: int) -> bool:
        return level <= self.threshold
