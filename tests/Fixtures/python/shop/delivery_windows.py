# A delivery window whose class docstring runs to a second paragraph about what else it does. Below it, the
# FIX: one paragraph saying what the class is, with its attributes in a section.
from dataclasses import dataclass


# @sin BloatedDocblock
@dataclass(frozen=True)
class DeliveryWindow:
    """The hours a courier may arrive at an address.

    It also knows which carriers serve the postcode, how the slot is priced at peak times, and when the
    warehouse has to have the parcel packed for it.
    """

    opens: int
    closes: int


# @fixed BloatedDocblock
@dataclass(frozen=True)
class CourierWindow:
    """The hours a courier may arrive at an address.

    Attributes:
        opens: the first hour, 0-23.
        closes: the last hour, 0-23.
    """

    opens: int
    closes: int
