# A courier slot exposes whether its van has left as a property named like a claim. A relation
# predicate beside it compares the slot against a postcode, so the third person is correct there.


class CourierSlot:
    def __init__(self, postcodes: frozenset[str], departed_at: float | None) -> None:
        self.postcodes = postcodes
        self.departed_at = departed_at

    # @sin BareStatePredicate
    @property
    def ships(self) -> bool:
        return self.departed_at is not None

    # @righteous BareStatePredicate
    def covers(self, postcode: str) -> bool:
        return postcode in self.postcodes
