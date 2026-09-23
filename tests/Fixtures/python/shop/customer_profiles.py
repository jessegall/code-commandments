# A customer profile given a new email by keyword, every other field carried across by name. Below it,
# the FIX: replace.
from dataclasses import dataclass, replace


@dataclass
class Profile:
    name: str
    email: str
    phone: str
    newsletter: bool

    def greeting(self) -> str:
        first, _, _ = self.name.partition(" ")
        return f"Dear {first}"

    def reachable_by_sms(self) -> bool:
        return self.phone.startswith("+31")

    def with_email(self, email: str) -> "Profile":
        # @sin HandRolledReplace
        return Profile(name=self.name, email=email, phone=self.phone, newsletter=self.newsletter)

    # @fixed HandRolledReplace
    def with_phone(self, phone: str) -> "Profile":
        return replace(self, phone=phone)
