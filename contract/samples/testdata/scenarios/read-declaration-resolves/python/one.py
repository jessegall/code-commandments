from enum import Enum


class Kind(Enum):
    Food = "food"  # @label food enum-case


class Ticket:
    def __init__(self, counter: str) -> None:
        self.counter = counter  # @label counter reference 14 inner
