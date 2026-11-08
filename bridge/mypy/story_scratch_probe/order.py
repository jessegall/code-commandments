class Refused(Exception):
    pass


def total(order):
    if order is None:
        raise Refused()
    return order.total
