class Refused(Exception):
    pass


def total(order):
    if order is None:
        raise Refused()
    else:
        return order.total
