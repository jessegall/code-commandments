"""Which courier a parcel travels with."""

from dataclasses import dataclass


@dataclass(frozen=True)
class Consignment:
    contents: str
    service: str


class CourierPicker:
    # @sin ComputedBooleanArgument
    def pick(self, fragile: bool, express: bool) -> str:
        match (fragile, express):
            case (True, True):
                return "white-glove"
            case (True, False):
                return "careful"
            case (False, True):
                return "rush"
            case _:
                return "standard"

    # @fixed ComputedBooleanArgument
    def pick_for(self, consignment: Consignment) -> str:
        return self.pick(consignment.contents in ("glass", "ceramic"), consignment.service == "next-day")


def route_all(consignments: list[Consignment], picker: CourierPicker) -> dict[str, str]:
    routes: dict[str, str] = {}
    for consignment in consignments:
        routes[consignment.contents] = picker.pick(consignment.contents in ("glass", "ceramic"), consignment.service == "next-day")
    return routes


def quote(consignment: Consignment, picker: CourierPicker) -> str:
    courier = picker.pick(express=consignment.service == "next-day", fragile=consignment.contents in ("glass", "ceramic"))
    return f"via {courier}"
