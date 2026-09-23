# Charging an order whose total has not been computed yet charges zero — an invented amount.


def charge(order, gateway) -> str:
    # @sin InventedDefault
    return gateway.charge(order.customer, amount=order.total or 0)
