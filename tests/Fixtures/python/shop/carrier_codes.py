# Carrier codes on a class spelled `(object)`. Below it, the FIX: an enum. And look-alikes: settings
# with annotated fields, and a class of documents rather than values.
from enum import StrEnum


# @sin ConstantClassEnum
class Carrier(object):
    POSTNL = "PNL"
    DHL = "DHL"


# @fixed ConstantClassEnum
class CarrierCode(StrEnum):
    POSTNL = "PNL"
    DHL = "DHL"


# @righteous ConstantClassEnum
class ShippingSettings:
    origin: str = "NL"
    parcels_per_crate: int = 12


# @righteous ConstantClassEnum
class ShippingQueries:
    OPEN = """
        select * from shipments
        where delivered_at is null
    """
    LATE = """
        select * from shipments
        where due_at < now()
    """
