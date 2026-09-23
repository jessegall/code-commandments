# The refund desk narrows the same payment shape a third time, holding back a refund while the customer is challenged.
from payment_events import CardPayment, Challenge


def refund_cents(event: object, limit_cents: int) -> int:
    # @sin RepeatedTypeGuard
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        return 0
    return limit_cents
