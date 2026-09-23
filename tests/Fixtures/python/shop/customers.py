# A customer's contact details — name, email and phone — travel side by side through two services.


class Newsletter:
    # @sin DataClump
    def subscribe(self, name: str, email: str, phone: str, opt_in: bool) -> None:
        self.list.add(email, name)


class Loyalty:
    # @sin DataClump
    def enrol(self, phone: str, name: str, email: str, opt_in: bool = False) -> None:
        self.members.add(name, email, phone, opt_in)
