# A label printer asked for each carrier's label size, the carriers it forgot answered with None. Below
# it, the FIX: an unhandled carrier fails loudly.
from enum import StrEnum


class LabelCarrier(StrEnum):
    POSTNL = "postnl"
    DHL = "dhl"
    UPS = "ups"


class UnhandledCarrier(Exception):
    @classmethod
    def of(cls, carrier: LabelCarrier) -> "UnhandledCarrier":
        return cls(f"no label size for {carrier}")


def label_size(carrier: LabelCarrier) -> str | None:
    # @sin MatchWildcardReturnsNone
    match carrier:
        case LabelCarrier.POSTNL:
            return "A6"
        case LabelCarrier.DHL:
            return "A5"
        case _:
            return None


# @fixed MatchWildcardReturnsNone
def label_size_for(carrier: LabelCarrier) -> str:
    match carrier:
        case LabelCarrier.POSTNL:
            return "A6"
        case LabelCarrier.DHL | LabelCarrier.UPS:
            return "A5"
        case _:
            raise UnhandledCarrier.of(carrier)
