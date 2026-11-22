type RebateClaim = { cents: bigint; issued: boolean; redeemed: boolean }

function formattedCredit(cents: bigint): string {
    return (cents / 100n).toString()
}

function claimedCents(claim: RebateClaim): bigint {
    return claim.cents
}

export function rebatesByPart(claims: Map<string, RebateClaim>): Map<string, string> {
    const displayed = new Map<string, string>()
    for (const [name, claim] of claims) {
        // @sin ConvertedArgument
        displayed.set(name, formattedCredit(claimedCents(claim)))
    }
    return displayed
}

export function describeLatestRebate(order: RebateClaim | undefined): string {
    if (order === undefined) return 'none issued'
    // @sin ConvertedArgument
    return formattedCredit(claimedCents(order))
}

function claimDue(claim: RebateClaim, planned: bigint): bigint {
    return claim.issued && !claim.redeemed ? planned : 0n
}

export function rebateInvoice(claim: RebateClaim, paid: RebateClaim): bigint {
    // @sin DerivedArgument
    const open = claimDue(claim, claim.cents)
    // @sin DerivedArgument
    return open + claimDue(paid, paid.cents)
}

function creditIsOpen(issued: boolean, paid: boolean): boolean {
    return issued && !paid
}

export function retainOutstanding(claims: RebateClaim[]): RebateClaim[] {
    return claims.filter(claim => {
        // @sin ComputedBooleanArgument
        return creditIsOpen(claim.issued, claim.redeemed)
    })
}

export function alreadySettled(claim: RebateClaim): boolean {
    // @sin ComputedBooleanArgument
    return !creditIsOpen(claim.issued, claim.redeemed)
}

// @sin FlagArgument
export function recordRebate(closed: boolean, statement: string[], code: string): void {
    if (closed) {
        statement.push(code + ' closed')
    } else {
        statement.unshift(code + ' pending')
    }
}

// @fixed FlagArgument
export function recordClosedRebate(statement: string[], code: string): void {
    statement.push(code + ' closed')
}

// @sin FilledParameter
// @sin FeatureEnvy
function issuePreparedCredit(claim: RebateClaim): void {
    if (claim.cents > 0n) {
        claim.issued = true
    }
}

export function issueClaim(claim: RebateClaim, report: (amount: bigint) => void): void {
    issuePreparedCredit(claim)
    if (claim.issued) report(claim.cents)
}

// @sin ParamResolvedFromParam
export function approvedClaim(
    candidates: Readonly<Record<number, RebateClaim>>, number: number,
): bigint {
    if (number < 1) return 0n
    const scheduled = candidates[number]
    return scheduled.redeemed ? 0n : scheduled.cents
}

export function netCredit(
    claim: RebateClaim, debit: bigint,
    // @sin UnusedParameter
    partner: string,
): string {
    const after = claim.cents - debit
    if (after <= 0n) return 'zero'
    return 'Credit ' + after
}
