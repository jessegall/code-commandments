"""A parcel's route between two depots, known once both ends are booked."""

from dataclasses import dataclass


@dataclass(frozen=True)
class Leg:
    origin: str
    destination: str


# @sin CoupledFields
class ParcelBooking:
    def __init__(self, reference: str, origin: str | None = None, destination: str | None = None) -> None:
        self.reference = reference
        self.origin = origin
        self.destination = destination

    def leg(self) -> Leg | None:
        if self.origin is None or self.destination is None:
            return None
        return Leg(self.origin, self.destination)


# @fixed CoupledFields
class RoutedParcel:
    def __init__(self, reference: str, leg: Leg | None = None) -> None:
        self.reference = reference
        self.leg = leg
