# Look-alikes that keep their `if`: a one-line filter is already as flat as it gets, and a search —
# a body that ends by leaving the loop — picks the one item it wanted.


# @righteous LoopWrappedInIf
def paid_orders(orders) -> list:
    paid = []
    for order in orders:
        if order.paid:
            paid.append(order)
    return paid


# @righteous LoopWrappedInIf
def first_open(orders):
    for order in orders:
        if order.open:
            order.touch()
            return order
    return None
