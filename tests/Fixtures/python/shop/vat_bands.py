# VAT bands as numbered constants on a documented class. Below it, the FIX: an `IntEnum` that also
# owns what each band means.
from enum import IntEnum


# @sin ConstantClassEnum
class VatBand:
    """The VAT bands a product can fall in."""

    ZERO = 0
    REDUCED = 1
    STANDARD = 2


# @fixed ConstantClassEnum
class TaxBand(IntEnum):
    ZERO = 0
    REDUCED = 1
    STANDARD = 2

    def rate(self) -> float:
        match self:
            case TaxBand.ZERO:
                return 0.0
            case TaxBand.REDUCED:
                return 0.09
            case TaxBand.STANDARD:
                return 0.21
