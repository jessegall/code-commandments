# A payment provider's webhook dispatched on the raw event string, each case a value the event enum
# already holds. Below it, the FIX: the string turned into the enum where it arrives, then dispatched.
from enum import StrEnum


class PaymentEvent(StrEnum):
    SUCCEEDED = "payment.succeeded"
    FAILED = "payment.failed"


def handle(event_type: str, payment_id: str, ledger) -> None:
    # @sin StringMatchMirrorsEnum
    match event_type:
        case "payment.succeeded":
            ledger.settle(payment_id)
        case "payment.failed":
            ledger.flag(payment_id)


# @fixed StringMatchMirrorsEnum
def handle_event(event: PaymentEvent, payment_id: str, ledger) -> None:
    match event:
        case PaymentEvent.SUCCEEDED:
            ledger.settle(payment_id)
        case PaymentEvent.FAILED:
            ledger.flag(payment_id)
