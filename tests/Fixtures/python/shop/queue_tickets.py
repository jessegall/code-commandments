"""Numbered tickets for the deli queue."""


class QueueTicket:
    def __init__(self, number: int, counter: str | None = None) -> None:
        self.number = number
        # @sin PhantomNullable
        self.counter = counter

    def called_at(self) -> str:
        return f"{self.number} to {self.counter.upper()}"


class ServedTicket:
    def __init__(self, number: int, counter: str | None = None) -> None:
        self.number = number
        # @righteous PhantomNullable
        self.counter = counter

    def called_at(self) -> str:
        if self.counter is None:
            return f"{self.number} waiting"
        return f"{self.number} to {self.counter.upper()}"
