# A checkout applies a discount rule by asking which kind of rule it holds, one returning `if` after
# another. Beside it, a constructor protocol that must sort out what it was handed.


class Rule:
    def __init__(self, amount: int) -> None:
        self.amount = amount


class PercentOff(Rule):
    pass


class FixedOff(Rule):
    pass


class Checkout:
    def __init__(self, total_cents: int) -> None:
        self.total_cents = total_cents

    def apply(self, rule: Rule) -> int:
        # @sin TypeSwitch
        if isinstance(rule, PercentOff):
            return self.total_cents * (100 - rule.amount) // 100
        if isinstance(rule, FixedOff):
            return max(0, self.total_cents - rule.amount)
        return self.total_cents


# @righteous TypeSwitch
class Price:
    def __init__(self, value: object) -> None:
        if isinstance(value, PercentOff):
            self.cents = value.amount
        elif isinstance(value, FixedOff):
            self.cents = -value.amount
        else:
            self.cents = 0
