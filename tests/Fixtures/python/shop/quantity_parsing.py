# A quantity parsed from a form field, the parse failure turned into the shop's own error with the
# original dropped from the chain. Below it, the FIX: raised `from` the failure it handles.


class BadQuantity(Exception):
    @classmethod
    def of(cls, raw: str) -> "BadQuantity":
        return cls(f"{raw!r} is not a quantity")


def quantity(raw: str) -> int:
    try:
        return int(raw)
    except ValueError:
        # @sin RaiseWithoutCause
        raise BadQuantity.of(raw)


# @fixed RaiseWithoutCause
def quantity_of(raw: str) -> int:
    try:
        return int(raw)
    except ValueError as error:
        raise BadQuantity.of(raw) from error
