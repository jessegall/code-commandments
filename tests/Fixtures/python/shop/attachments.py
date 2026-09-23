# Mail takes its attachments as an optional list defaulted to None, then turns the None back into
# "nothing" in the loop header. Below it, the FIX: the empty default lives in the signature.


class Mailer:
    def __init__(self, outbox) -> None:
        self.outbox = outbox

    def send(self, to: str, body: str, files: list | None = None) -> None:
        message = self.outbox.compose(to, body)
        # @sin CoalescedLoopSubject
        for path in files or []:
            message.attach(path)
        self.outbox.deliver(message)

    # @fixed CoalescedLoopSubject
    def send_with(self, to: str, body: str, files: tuple[str, ...] = ()) -> None:
        message = self.outbox.compose(to, body)
        for path in files:
            message.attach(path)
        self.outbox.deliver(message)
