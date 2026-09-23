# A refund is refused outright past its window — and the refund itself is then filed under an
# `else:` the `raise` already made unnecessary.


class RefundRefused(Exception):
    pass


def refund(order, today) -> int:
    # @sin RedundantElse
    if (today - order.delivered).days > 30:
        raise RefundRefused(order.number)
    else:
        amount = order.total()
        order.mark_refunded(amount)
        return amount
