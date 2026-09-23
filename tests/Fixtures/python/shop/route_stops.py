"""Delivery routes and the stops a driver ticks off."""

from dataclasses import dataclass, field


@dataclass
class Stop:
    done: bool = False


@dataclass
class Schedule:
    stops: dict[str, Stop] = field(default_factory=dict)

    def find(self, reference: str) -> Stop:
        return self.stops[reference]


@dataclass
class Route:
    schedule: Schedule = field(default_factory=Schedule)
    driver: str = ""


class Dispatcher:
    def __init__(self) -> None:
        self.ticked = 0

    # @sin ParamResolvedFromParam
    def tick(self, route: Route, reference: str) -> None:
        stop = route.schedule.find(reference)
        stop.done = True
        self.ticked += 1

    # @fixed ParamResolvedFromParam
    def tick_stop(self, stop: Stop) -> None:
        stop.done = True
        self.ticked += 1
