# A returns batch answers whether it still waits on the warehouse with a compound verb phrase.


class ReturnsBatch:
    def __init__(self, order_ref: str, pending_returns: int) -> None:
        self.order_ref = order_ref
        self.pending_returns = pending_returns

    # @sin BareStatePredicate
    def waits_on_warehouse(self) -> bool:
        return self.pending_returns > 0

    def outstanding(self) -> int:
        return self.pending_returns
