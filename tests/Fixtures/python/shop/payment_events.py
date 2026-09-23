# Payment events arrive as a union; two handlers narrow to a card payment carrying a 3-D Secure
# challenge the same way. The FIX: the payment answers whether it is challenged, and a caller narrows
# once and asks it.


class Challenge:
    def __init__(self, url: str) -> None:
        self.url = url


class CardPayment:
    def __init__(self, amount_cents: int, step: object) -> None:
        self.amount_cents = amount_cents
        self.step = step

    # @fixed RepeatedTypeGuard
    @property
    def is_challenged(self) -> bool:
        return isinstance(self.step, Challenge)


def challenge_url(event: object) -> str:
    # @sin RepeatedTypeGuard
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        return event.step.url
    return ""


def notify_customer(event: object, outbox: list[str]) -> None:
    # @sin RepeatedTypeGuard
    if isinstance(event, CardPayment) and isinstance(event.step, Challenge):
        outbox.append(event.step.url)


# @fixed RepeatedTypeGuard
def challenge_link(event: object) -> str:
    if isinstance(event, CardPayment) and event.is_challenged:
        return str(event.step)
    return ""


# @righteous RepeatedTypeGuard
def is_card(event: object) -> bool:
    return isinstance(event, CardPayment) and event.amount_cents > 0
