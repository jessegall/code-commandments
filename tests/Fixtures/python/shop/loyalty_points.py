# Two loyalty rules ask whether a member may redeem — one inline, one through a local and in the other
# order, which is still the same question. Below them, the FIX: the question named on the member, and
# a value reached through `and` that is stored, not asked.


class Member:
    def __init__(self, points: int, frozen: bool, tier: str) -> None:
        self.points = points
        self.frozen = frozen
        self.tier = tier


def redeem(member: Member, cost: int) -> int:
    # @sin RepeatedGuard
    if member.points >= cost and not member.frozen:
        return member.points - cost
    raise ValueError(cost)


def preview(member: Member, cost: int) -> str:
    enough = member.points >= cost
    # @sin RepeatedGuard
    return "redeemable" if not member.frozen and enough else "locked"


# @fixed RepeatedGuard
class Account:
    def __init__(self, points: int, frozen: bool) -> None:
        self.points = points
        self.frozen = frozen

    def can_redeem(self, cost: int) -> bool:
        return self.points >= cost and not self.frozen

    def redeem(self, cost: int) -> int:
        if self.can_redeem(cost):
            return self.points - cost
        raise ValueError(cost)


# @righteous RepeatedGuard
def tier_label(member: Member | None) -> str:
    tier = member and member.tier.upper()
    return str(tier)


def tier_badge(member: Member | None) -> str:
    tier = member and member.tier.upper()
    return f"[{tier}]"
