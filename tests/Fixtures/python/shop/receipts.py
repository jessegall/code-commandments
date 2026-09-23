# Sending the receipt: an order without an email is sent to "" — an address the mailer cannot tell
# from a real one, so the failure lands far from here. Below it, the FIX: the missing case is decided
# where it is known.


# @fixed InventedDefault
class NoEmail(LookupError):
    @classmethod
    def on(cls, order) -> "NoEmail":
        return cls(f"order {order.number} has no email to send the receipt to")


def send_receipt(order, mailer) -> None:
    # @sin InventedDefault
    mailer.send(order.email or "", subject=f"Receipt {order.number}")


# @fixed InventedDefault
def mail_receipt(order, mailer) -> None:
    if order.email is None:
        raise NoEmail.on(order)
    mailer.send(order.email, subject=f"Receipt {order.number}")
