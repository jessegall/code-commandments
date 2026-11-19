from dataclasses import dataclass


@dataclass
class Member:
    tier: str
    points: int


def redeem(member: Member, cost: int) -> str:
    # @sin DuplicatedBranchTail
    if member.points < cost:
        print(member.points)
        result = "spent"
    else:
        member.points -= cost
        result = "spent"

    return result


def expire(member: Member, lapsed: bool) -> Member:
    if not lapsed:
        return member
    member.points = 0
    return member
    # @sin UnreachableStatement
    member.tier = "bronze"


# @fixed UnreachableStatement
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
