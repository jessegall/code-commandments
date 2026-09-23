# A wishlist whose method explains, above one line, what that line used to be.


class Wishlist:
    def __init__(self, owner: str) -> None:
        self.owner = owner
        self.skus: list[str] = []

    def add(self, sku: str) -> None:
        # used to be a set, which lost the order customers added them in
        # @sin ArchaeologyComment
        self.skus.append(sku)

    # @righteous ArchaeologyComment
    def remove(self, sku: str) -> None:
        # a sku can be wished for twice; only the first goes
        self.skus.remove(sku)
