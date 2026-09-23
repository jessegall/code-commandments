# A basket total whose docstring is nothing but Sphinx fields restating its annotations.


class Basket:
    def __init__(self, lines: list[int]) -> None:
        self.lines = lines

    # @sin CeremonyDocblock
    def total(self, discount: int) -> int:
        """
        :param discount:
        :type discount: int
        :rtype: int
        """
        return sum(self.lines) - discount
