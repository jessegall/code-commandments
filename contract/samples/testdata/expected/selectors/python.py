from decimal import Decimal


class Account:
    def __init__(self) -> None:
        self.total = Decimal(0)

    def settle(self, amounts: list[int]) -> Decimal:
        for amount in amounts:
            if amount > 0 and amount < 100:
                self.total += amount
                continue
            break
        try:
            record(self.total)
        except OSError as error:
            raise RuntimeError("not recorded") from error
        return self.total
