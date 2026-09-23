# A shipping notice sent with a blank tracking code passed by position, the notice promising one it
# does not have. Below it, the FIX: the real code fetched before the notice is built.
from dataclasses import dataclass


@dataclass(frozen=True)
class ShippingNotice:
    order_number: str
    tracking_code: str
    carrier: str


class Notifier:
    def __init__(self, mailer, carriers) -> None:
        self.mailer = mailer
        self.carriers = carriers

    def notify(self, order) -> None:
        # @sin PlaceholderFilledData
        notice = ShippingNotice(order.number, "", order.carrier)
        self.mailer.send(order.email, notice)

    # @fixed PlaceholderFilledData
    def notify_tracked(self, order) -> None:
        code = self.carriers.track(order.carrier, order.number)
        self.mailer.send(order.email, ShippingNotice(order.number, code, order.carrier))
