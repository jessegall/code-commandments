# Orders taken off the pending list while the list is being walked: remove moves every later order up one, so
# the walk reads past one of them and it stays pending for ever. Below it, the FIX: decide while walking,
# change once the walk is over.


class PendingOrders:
    def __init__(self, pending: list[str]) -> None:
        self.pending = pending

    def release(self, paid: list[str]) -> None:
        for order in self.pending:
            if order in paid:
                # @sin CollectionMutatedWhileIterated
                self.pending.remove(order)

    # @fixed CollectionMutatedWhileIterated
    def release_paid(self, paid: list[str]) -> None:
        released = [order for order in self.pending if order in paid]
        for order in released:
            self.pending.remove(order)

    # @righteous CollectionMutatedWhileIterated
    def release_first(self, paid: list[str]) -> str:
        for order in self.pending:
            if order in paid:
                self.pending.remove(order)
                return order

        return ""
