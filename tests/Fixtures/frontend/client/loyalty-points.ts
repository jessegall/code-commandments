// Points a member collects, multiplied by the tier they hold and spent on rewards.
export enum Tier {
    Bronze = 'bronze',
    Silver = 'silver',
    Gold = 'gold',
    Platinum = 'platinum',
}

export interface Member {
    tier: Tier
    points: number
}

export function bonus(member: Member): number {
    // @sin SubjectLadder
    if ('bronze' === member.tier) {
        return member.points
    } else if ('silver' === member.tier) {
        return member.points * 2
    } else if ('gold' === member.tier) {
        return member.points * 3
    } else if ('platinum' === member.tier) {
        return member.points * 5
    }
    throw new Error('a member holds no tier')
}

export function redeem(member: Member, cost: number): string {
    // @sin DuplicatedBranchTail
    // @sin RedundantElse
    if (member.points < cost) {
        console.info(member.points)
        return 'spent'
    } else {
        member.points -= cost
        return 'spent'
    }
}

export function expire(member: Member, lapsed: boolean): Member {
    if (!lapsed) {
        return member
    }
    member.points = 0
    return member
    // @sin UnreachableStatement
    member.tier = Tier.Bronze
}

// @fixed UnreachableStatement
export function expireLapsed(member: Member, lapsed: boolean): Member {
    if (!lapsed) {
        return member
    }
    member.points = 0

    return member
}

export function award(members: Member[], points: number): void {
    for (const member of members) {
        // @sin RedundantElse
        if (member.tier === Tier.Platinum) {
            continue
        } else {
            member.points += points
        }
    }
}

export function settle(members: Member[]): number {
    let settled = 0
    for (let index = 0; index < members.length; index++) {
        // @sin LoopInvertedGuard
        if (members[index].points < 0) {
            members[index].points = 0
            settled++
        }
    }

    return settled
}

// @fixed LoopInvertedGuard
export function settleNegative(members: Member[]): number {
    let settled = 0
    for (const member of members) {
        if (member.points >= 0) {
            continue
        }
        member.points = 0
        settled++
    }

    return settled
}

// @righteous LoopInvertedGuard
export function audit(members: Member[]): number {
    let flagged = 0
    for (const member of members) {
        if (member.points < 0) {
            flagged++
        }
    }

    return flagged
}

// @righteous RedundantElse
export function describe(member: Member): string {
    if (member.points > 1000) {
        console.info(member.tier)
    } else {
        member.points += 1
    }

    return member.tier
}

// @righteous UnreachableStatement
export function render(member: Member): string {
    return describeTier(member.tier)

    function describeTier(tier: Tier): string {
        return tier.toUpperCase()
    }
}

export function greeting(member: Member, rewards: Member[]): string[] {
    return rewards.map((other) =>
        // @sin NestedTernary
        other.points > member.points ? 'ahead' : other.points === member.points ? 'level' : 'behind',
    )
}

// @righteous NestedTernary
export function standing(member: Member, rewards: Member[]): string {
    const ahead = rewards.some((other) => other.points > member.points) ? 'chasing' : 'leading'

    return member.points > 0 ? ahead : 'new'
}

export function celebrate(member: Member, log: string[]): void {
    // @sin TernaryStatement
    member.tier === Tier.Gold ? console.info(member.points) : log.push(member.tier)
}

// @righteous TernaryStatement
export function badge(member: Member): string {
    const badge = member.tier === Tier.Gold ? 'gold' : 'plain'

    return badge
}

export function remind(member: Member, log: string[]): void {
    // @sin ShortCircuitStatement
    member.points < 100 && member.tier !== Tier.Bronze && log.push(member.tier)
}

// @righteous ShortCircuitStatement
export function eligible(member: Member): boolean {
    return member.points >= 100 && member.tier !== Tier.Bronze
}
