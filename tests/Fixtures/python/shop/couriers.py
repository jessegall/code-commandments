# A courier booking where a blank tracking code means "none issued yet", asked by truthiness. Below it,
# the FIX: `None` for not yet. And a blank that is never asked — a separator — left as it is.


class Courier:
    def __init__(self, api) -> None:
        self.api = api

    # @sin BlankStringDefault
    def book(self, parcel: str, tracking: str = "") -> str:
        if not tracking:
            tracking = self.api.issue(parcel)
        return self.api.book(parcel, tracking)

    # @fixed BlankStringDefault
    def book_tracked(self, parcel: str, tracking: str | None = None) -> str:
        if tracking is None:
            tracking = self.api.issue(parcel)
        return self.api.book(parcel, tracking)


# @righteous BlankStringDefault
def manifest(lines: list, separator: str = "") -> str:
    return separator.join(lines)
