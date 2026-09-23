# Two listeners that act only when an order changes — each wrote the same filter-then-dispatch
# override, so a third kind of change has to be taught to both.


class Listener:
    def handle(self, event) -> None:
        pass


class ReceiptMailer(Listener):
    # @sin DuplicateFunction
    def on_event(self, event) -> None:
        if event.kind == "order" and event.action in ("created", "updated"):
            self.handle(event)
            event.acknowledge(self.__class__.__name__)


class StockSync(Listener):
    # @sin DuplicateFunction
    def on_event(self, event) -> None:
        if event.kind == "order" and event.action in ("created", "updated"):
            self.handle(event)
            event.acknowledge(self.__class__.__name__)
