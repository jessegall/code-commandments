from decimal import Decimal


class Account:
    def __init__(self) -> None:
        self.total = Decimal(0)

    def settle(self, amounts: list[int]) -> Decimal:
        # @contract kind/loop
        # @contract fact/loop/form = "for-each"
        for amount in amounts: # @label each loop
            # @contract role/if/condition -> both
            if amount > 0 and amount < 100: # @label both logical
                self.total += amount # @contract kind/assignment
                continue # @contract link/continue/destination -> each
            break # @contract link/break/destination -> each
        try:
            record(self.total)
        except OSError as error:
            raise RuntimeError("not recorded") from error
        return self.total
