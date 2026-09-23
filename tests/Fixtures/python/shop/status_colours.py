# An order's badge picked by a chain of conditional expressions, each rung testing the status again.
# Below it, the FIX: a `match` over the status, one case a line.


def badge(order) -> str:
    # @sin NestedConditional
    return "green" if order.status == "paid" else "amber" if order.status == "pending" else "red"


# @fixed NestedConditional
def badge_for(order) -> str:
    match order.status:
        case "paid":
            return "green"
        case "pending":
            return "amber"
        case _:
            return "red"
