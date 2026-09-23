# A loyalty card dataclass that grows a field at the bottom, under the method that uses it. Below it,
# the FIX: every field at the top.
from dataclasses import dataclass


@dataclass
class LoyaltyCard:
    number: str
    points: int

    def redeemable(self) -> bool:
        return self.points >= self.minimum

    # @sin MemberAfterMethod
    minimum: int = 100


# @fixed MemberAfterMethod
@dataclass
class StampCard:
    number: str
    stamps: int
    minimum: int = 10

    def full(self) -> bool:
        return self.stamps >= self.minimum
