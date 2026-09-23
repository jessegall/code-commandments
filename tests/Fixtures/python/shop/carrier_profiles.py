# A carrier profile with a Final code declared under its attributes. Below it, the FIX: the Final
# first. And look-alikes: a class whose head holds only constants, and an enum's members.
from enum import Enum
from typing import Final


class CarrierProfile:
    name = "PostNL"
    max_kg = 30
    # @sin MemberOutOfOrder
    CODE: Final = "PNL"


# @fixed MemberOutOfOrder
class CourierProfile:
    CODE: Final = "DHL"
    name = "DHL"
    max_kg = 31


# @righteous MemberOutOfOrder
class ParcelLimits:
    MAX_KG = 30
    MAX_CM = 120

    def fits(self, kg: float, cm: float) -> bool:
        return kg <= self.MAX_KG and cm <= self.MAX_CM


# @righteous MemberOutOfOrder
class DeliveryWindow(Enum):
    _ignore_ = "spare"
    MORNING = 1
    EVENING = 2
