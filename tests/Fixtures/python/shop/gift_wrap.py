"""Gift wrap, decided from an order and a parcel together."""

from dataclasses import dataclass


@dataclass(frozen=True)
class GiftOrder:
    gift: bool


@dataclass(frozen=True)
class WrapParcel:
    weight: int


class Wrapper:
    # @righteous ComputedBooleanArgument
    def paper(self, gift: bool, heavy: bool) -> str:
        return "kraft" if heavy or gift else "none"


def wrap(order: GiftOrder, parcel: WrapParcel, wrapper: Wrapper) -> str:
    return wrapper.paper(order.gift, parcel.weight > 10)


def rewrap(order: GiftOrder, parcel: WrapParcel, wrapper: Wrapper) -> str:
    return wrapper.paper(gift=order.gift, heavy=parcel.weight > 10)
