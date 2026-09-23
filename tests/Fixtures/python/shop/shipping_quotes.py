# A shipping quote whose docstring repeats its annotations in Google style. Below it, the FIX: the sentence
# that says what it does, and a description only where a type cannot say it.


class Parcel:
    def __init__(self, weight_grams: int) -> None:
        self.weight_grams = weight_grams


# @sin CeremonyDocblock
def quote(parcel: Parcel, zone: str) -> int:
    """
    Args:
        parcel (Parcel):
        zone (str):

    Returns:
        int
    """
    return 500 + parcel.weight_grams // 100


# @fixed CeremonyDocblock
def quote_cents(parcel: Parcel, zone: str) -> int:
    """The price of sending the parcel to the zone, in cents.

    Args:
        zone: a carrier zone code, such as "EU-1".
    """
    return 500 + parcel.weight_grams // 100
