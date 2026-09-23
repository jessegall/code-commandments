# A packing slip and an invoice sheet each lay out order lines, and each class wrote its own copy of
# the same loop — so a change to how a line reads lands in one and not the other.

from shop.orders import Line


class PackingSlip:
    # @sin DuplicateFunction
    def rows(self, lines: list[Line]) -> list[str]:
        rows = []
        for line in lines:
            if line.quantity <= 0:
                continue
            rows.append(f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}")
        return rows


class InvoiceSheet:
    # @sin DuplicateFunction
    def line_items(self, lines: list[Line]) -> list[str]:
        rows = []
        for line in lines:
            if line.quantity <= 0:
                continue
            rows.append(f"{line.quantity} x {line.sku}: {line.total() / 100:.2f}")
        return rows
