from dataclasses import dataclass


@dataclass
class Member:
    tier: str
    points: int


# @sin FeatureEnvy
def redeem(member: Member, cost: int) -> str:
    # @sin DuplicatedBranchTail
    if member.points < cost:
        print(member.points)
        result = "spent"
    else:
        member.points -= cost
        result = "spent"

    return result


# @sin FeatureEnvy
def expire(member: Member, lapsed: bool) -> Member:
    if not lapsed:
        return member
    member.points = 0
    return member
    # @sin UnreachableStatement
    member.tier = "bronze"


# @fixed UnreachableStatement
# @sin FeatureEnvy
def expire_lapsed(member: Member, lapsed: bool) -> Member:
    if not lapsed:
        return member
    member.points = 0

    return member


# @righteous UnreachableStatement
def render(member: Member) -> str:
    return describe(member.tier)


def describe(tier: str) -> str:
    return tier.upper()


def points_for(spent: list[int], bonus: int) -> int:
    for amount in spent:
        # @sin ReassignedParameter
        bonus += amount // 10
    return bonus
