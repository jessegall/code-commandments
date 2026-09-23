"""Seats at the café counter, booked by number."""

from dataclasses import dataclass, field


@dataclass
class Seat:
    taken: bool = False
    guest: str = ""


@dataclass
class SeatMap:
    seats: dict[int, Seat] = field(default_factory=dict)
    opened: str = ""


# @sin ParamResolvedFromParam
def book(seat_map: SeatMap, number: int, guest: str) -> bool:
    seat = seat_map.seats[number]
    if seat.taken:
        return False
    seat.taken, seat.guest = True, guest
    return True


# @fixed ParamResolvedFromParam
def book_seat(seat: Seat, guest: str) -> bool:
    if seat.taken:
        return False
    seat.taken, seat.guest = True, guest
    return True
