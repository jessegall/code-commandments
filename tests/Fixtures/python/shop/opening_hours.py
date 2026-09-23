"""A shop's opening hours, kept per weekday."""

from dataclasses import dataclass


@dataclass(frozen=True)
class Hours:
    opens: int
    closes: int

    def covers(self, hour: int) -> bool:
        return self.opens <= hour < self.closes


# @sin CoupledFields
class Weekday:
    def __init__(self, name: str, opens: int, closes: int) -> None:
        self.name = name
        self.opens = opens
        self.closes = closes

    def hours(self) -> Hours:
        return Hours(self.opens, self.closes)

    def label(self) -> str:
        opens, closes = (self.opens, self.closes)
        return f"{self.name}: {opens}-{closes}"


# @fixed CoupledFields
class OpenWeekday:
    def __init__(self, name: str, hours: Hours) -> None:
        self.name = name
        self.hours = hours

    def label(self) -> str:
        return f"{self.name}: {self.hours.opens}-{self.hours.closes}"
