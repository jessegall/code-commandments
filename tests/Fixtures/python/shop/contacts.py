# Look-alikes: the value being born takes its fields one by one — its `__init__` and a named
# constructor are where the loose values become the type.


class Contact:
    # @righteous DataClump
    def __init__(self, name: str, email: str, phone: str, opt_in: bool) -> None:
        self.name = name
        self.email = email
        self.phone = phone
        self.opt_in = opt_in

    # @righteous DataClump
    @classmethod
    def of(cls, name: str, email: str, phone: str, opt_in: bool) -> "Contact":
        return cls(name, email, phone, opt_in)
