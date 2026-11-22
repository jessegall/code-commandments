"""Which courier a parcel travels with."""

from dataclasses import dataclass


@dataclass(frozen=True)
class Consignment:
    contents: str
    service: str


class CourierPicker:
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

    def pick_for(self, consignment: Consignment) -> str:
        # @sin ComputedBooleanArgument
        return self.pick(consignment.contents in ("glass", "ceramic"), consignment.service == "next-day")

    # @fixed ComputedBooleanArgument
    def pick_of(self, consignment: Consignment) -> str:
        fragile = consignment.contents in ("glass", "ceramic")
        if consignment.service == "next-day":
            return "white-glove" if fragile else "rush"
        return "careful" if fragile else "standard"


def route_all(consignments: list[Consignment], picker: CourierPicker) -> dict[str, str]:
    routes: dict[str, str] = {}
    for consignment in consignments:
        # @sin ComputedBooleanArgument
        routes[consignment.contents] = picker.pick(consignment.contents in ("glass", "ceramic"), consignment.service == "next-day")
    return routes


def quote(consignment: Consignment, picker: CourierPicker) -> str:
    # @sin ComputedBooleanArgument
    courier = picker.pick(express=consignment.service == "next-day", fragile=consignment.contents in ("glass", "ceramic"))
    return f"via {courier}"
