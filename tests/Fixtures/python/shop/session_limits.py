# A session's lifetime as a property that multiplies two literals on every read. Below it, the FIX:
# a class-level constant, computed once where it is declared.
from typing import ClassVar


class Session:
    def __init__(self, user: str) -> None:
        self.user = user

    # @sin ConstantProperty
    @property
    def lifetime_seconds(self) -> int:
        """How long a session stays valid."""
        return 60 * 60 * 8

    def expires_after(self, started: int) -> int:
        return started + self.lifetime_seconds


# @fixed ConstantProperty
class ShopSession:
    LIFETIME_SECONDS: ClassVar[int] = 60 * 60 * 8

    def __init__(self, user: str) -> None:
        self.user = user

    def expires_after(self, started: int) -> int:
        return started + self.LIFETIME_SECONDS
