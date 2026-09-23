# Look-alikes: a subclass repeats the signature its base declares — an override is that one method
# written again by contract, not a new place the values travel.


class Store:
    def ship_to(self, street: str, city: str, postcode: str, express: bool) -> str:
        raise NotImplementedError


class WebStore(Store):
    # @righteous DataClump
    def ship_to(self, street: str, city: str, postcode: str, express: bool) -> str:
        return f"web: {street}, {postcode} {city}{' (express)' if express else ''}"


class PhoneStore(Store):
    # @righteous DataClump
    def ship_to(self, street: str, city: str, postcode: str, express: bool) -> str:
        return f"phone: {street}, {postcode} {city}"
