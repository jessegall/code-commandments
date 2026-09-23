# The FIX for the documents' copied loop: the line layout lives in ONE function, and every document
# that lists order lines calls it instead of carrying its own copy.

from shop.orders import Line


# @fixed DuplicateFunction
def describe_lines(lines: list[Line]) -> list[str]:
    return [f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}" for line in lines if line.quantity > 0]


class DeliveryNote:
    # @fixed DuplicateFunction
    def rows(self, lines: list[Line]) -> list[str]:
        return describe_lines(lines)


class ReturnForm:
    # @fixed DuplicateFunction
    def line_items(self, lines: list[Line]) -> list[str]:
        return describe_lines(lines)
