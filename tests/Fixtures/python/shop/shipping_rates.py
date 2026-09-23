# A shipping quote that asks each parcel what kind it is to price it. Below it, the FIX: each parcel kind
# answers its own rate, and the quote tells it to.


class Parcel:
    def __init__(self, weight_grams: int) -> None:
        self.weight_grams = weight_grams

    def rate_cents(self) -> int:
        return 500


class Letter(Parcel):
    def rate_cents(self) -> int:
        return 120


class Pallet(Parcel):
    def rate_cents(self) -> int:
        return 4000 + self.weight_grams // 100


def quote(parcel: Parcel) -> int:
    # @sin TypeSwitch
    if isinstance(parcel, Letter):
        return 120
    elif isinstance(parcel, Pallet):
        return 4000 + parcel.weight_grams // 100
    return 500


# @fixed TypeSwitch
def quote_told(parcel: Parcel) -> int:
    return parcel.rate_cents()
