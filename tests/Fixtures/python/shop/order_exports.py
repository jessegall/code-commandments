# An order export whose method points, through a titled reference, at a module the shop does not hold.
# Beside it, a reference into a package the shop does not own, which cannot be checked from here.


class OrderExport:
    def __init__(self, orders: list) -> None:
        self.orders = orders

    # @sin DanglingDocReference
    def to_csv(self) -> str:
        """The orders as CSV, in the layout of :mod:`the ledger <shop.accounting.ledger>`."""
        return "\n".join(str(order) for order in self.orders)

    # @righteous DanglingDocReference
    def to_json(self) -> str:
        """The orders as JSON, written by :func:`json.dumps`."""
        return str(self.orders)
