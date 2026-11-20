from typing import Protocol


class ParcelNotice(Protocol):
    label: str


class WaitingParcelNotice(ParcelNotice):
    label = "Waiting"


class DelayedParcelNotice(ParcelNotice):
    label = "Delayed"


class ReturnedParcelNotice(ParcelNotice):
    label = "Returned"


class ShippedParcelNotice(ParcelNotice):
    label = "Shipped"


class DeliveredParcelNotice(ParcelNotice):
    label = "Delivered"


def all_types() -> list[type[ParcelNotice]]:
    # @sin ListMirrorsFolder
    return [WaitingParcelNotice, DelayedParcelNotice, ReturnedParcelNotice, ShippedParcelNotice, DeliveredParcelNotice]


# @righteous ListMirrorsFolder
def starter_types() -> list[type[ParcelNotice]]:
    return [WaitingParcelNotice, DelayedParcelNotice]
