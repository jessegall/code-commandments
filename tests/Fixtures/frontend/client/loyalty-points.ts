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
    // @sin GenericException
    // @sin MessageAtThrow
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

export function pointsFor(spent: number[], bonus: number): number {
    for (const amount of spent) {
        // @sin ReassignedParameter
        bonus += Math.floor(amount / 10)
    }

    return bonus
}

export function visitPoints(members: Member[], visits: Map<Tier, number[]>): number {
    let earned = 0
    members.forEach((member) => {
        if (member.points > 0) {
            // @sin CoalescedLoopSubject
            for (const visit of visits.get(member.tier) ?? []) {
                if (visit > 0) {
                    // @sin DeepNesting
                    if (visit < member.points) {
                        earned += visit
                    }
                }
            }
        }
    })

    return earned
}

export function pointsAcrossDays(from: Date, until: Date, earned: Map<string, number>): number {
    let points = 0
    // @sin NonCountingFor
    for (let day = from; day < until; day = new Date(day.getTime() + 86400000)) {
        points += earned.get(day.toDateString()) ?? 0
    }

    return points
}

export function tierUnset(tiers: Map<string, Tier>, name: string): boolean {
    const tier = tiers.get(name)
    // @sin CancelledCoalesce
    const unset = (tier ?? 'none') == 'none'

    return unset
}

export class PointsLost extends Error {
    static forMember(memberId: string): PointsLost {
        return new PointsLost(`the points of member ${memberId} were lost`)
    }
}

export function savedVisits(store: { visits(): string[] }): string[] {
    try {
        return store.visits()
    }
    // @sin SwallowCatch
    catch (error) {
        return []
    }
}

export function awardedPoints(memberId: string, ledger: { award(id: string): number }): number {
    try {
        return ledger.award(memberId)
    }
    // @sin RethrownUnchanged
    catch (reason) {
        throw reason
    }
}

export function redeemedPoints(memberId: string, ledger: { redeem(id: string): number }): number {
    try {
        return ledger.redeem(memberId)
    } catch (error) {
        // @sin WrappingWithoutCause
        throw new PointsLost(memberId)
    }
}

export function tierThreshold(tier: string, thresholds: Map<string, number>): number {
    const threshold = thresholds.get(tier)
    if (threshold === undefined) {
        // @sin GenericException
        throw new Error()
    }

    return threshold
}

export function spendablePoints(balance: number): number {
    if (balance < 0) {
        // @sin MessageAtThrow
        throw new PointsLost('a balance cannot be negative')
    }

    return balance
}

// @fixed MessageAtThrow
export function spendableBalance(balance: number, memberId: string): number {
    if (balance < 0) {
        throw PointsLost.forMember(memberId)
    }

    return balance
}
