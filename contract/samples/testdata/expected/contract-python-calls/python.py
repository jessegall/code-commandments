class Money:
    # @contract fact/function/form = "constructor"
    # @contract fact/type-expression/form at 40 = "keyword"
    def __init__(self, amount: int) -> None:
        # @contract fact/reference/form at 9 = "self"
        self.amount = amount


# @label total_fn function
def total(rate: int, bonus: int = 0) -> int:
    return rate + bonus


def run(pair, named):
    # @contract kind/construction
    # @contract role/construction/callee -> money_ref
    # @contract role/construction/arguments -> money_arg
    # @contract kind/argument
    # @contract role/argument/value -> three_lit
    # @label money_ref reference 13
    # @label money_arg argument 19
    # @label three_lit literal 19
    first = Money(3)
    # @contract kind/call
    # @contract role/call/callee -> total_ref
    # @contract role/call/arguments -> [a_first, a_bonus]
    # @contract fact/argument/label at 23 = "bonus"
    # @contract fact/argument/spread at 20 = "none"
    # @contract fact/*/target on call = {"name":"total"}
    # @contract fact/*/refers on reference at 14 -> total_fn
    # @label total_ref reference 14
    # @label a_first argument 20
    # @label a_bonus argument 23
    second = total(1, bonus=2)
    # @contract fact/argument/spread = "positional"
    third = total(*pair)
    # @contract fact/argument/spread = "keyed"
    fourth = total(**named)
    return first, second, third, fourth
