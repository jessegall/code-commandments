# Which carrier ships a parcel is decided by its service level, one `==` at a time — a dispatch
# written as a ladder. Below it, the FIX: the closed set is an Enum that answers for each case.

from enum import Enum


def carrier_for(service: str) -> str:
    # @sin SubjectLadder
    if service == "express":
        return "dhl"
    elif service == "standard":
        return "postnl"
    elif service == "economy":
        return "dpd"
    elif service == "freight":
        return "schenker"
    raise ValueError(service)


# @fixed SubjectLadder
class Service(Enum):
    EXPRESS = "express"
    STANDARD = "standard"
    ECONOMY = "economy"
    FREIGHT = "freight"

    def carrier(self) -> str:
        return {
            Service.EXPRESS: "dhl",
            Service.STANDARD: "postnl",
            Service.ECONOMY: "dpd",
            Service.FREIGHT: "schenker",
        }[self]
