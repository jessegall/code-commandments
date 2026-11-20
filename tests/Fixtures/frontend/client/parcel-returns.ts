// Returns a customer sends back, priced and shelved by the reason they give.
export interface ParcelReturn {
    reason: string
    cents: number
    opened: boolean
}

export function refundShare(item: ParcelReturn): number {
    // @sin SubjectLadder
    if (item.reason === 'damaged') {
        return 100
    } else if (item.reason === 'late') {
        return 80
    } else if (item.reason === 'wrong-size') {
        return 60
    } else if (item.reason === 'changed-mind') {
        return 40
    }

    return 0
}

const shares: Record<string, number> = { damaged: 100, late: 80, 'wrong-size': 60, 'changed-mind': 40 }

// @fixed SubjectLadder
export function refundShareByReason(item: ParcelReturn): number {
    return shares[item.reason] ?? 0
}

export function restock(item: ParcelReturn, shelf: Map<string, number>, log: string[]): void {
    // @sin DuplicatedBranchTail
    if (item.opened) {
        shelf.set('seconds', (shelf.get('seconds') ?? 0) + 1)
        log.push(item.reason)
    } else {
        shelf.set('new', (shelf.get('new') ?? 0) + 1)
        log.push(item.reason)
    }
}

export function refund(item: ParcelReturn): number {
    if (item.cents <= 0) {
        // @sin GenericException
        // @sin MessageAtThrow
        throw new Error('nothing to refund')
        // @sin UnreachableStatement
        item.cents = 0
    }

    return item.cents
}

export function payable(item: ParcelReturn): boolean {
    // @sin RedundantElse
    if (item.cents <= 0) {
        return false
    } else {
        item.opened = false

        return true
    }
}

export function sortReturns(items: ParcelReturn[], shelf: Map<string, number>): number {
    let shelved = 0
    for (const item of items) {
        // @sin LoopInvertedGuard
        if (!item.opened) {
            shelf.set(item.reason, (shelf.get(item.reason) ?? 0) + 1)
            shelved++
        }
    }

    return shelved
}

// @righteous SubjectLadder
export function triage(item: ParcelReturn): string {
    if (item.reason === 'damaged') {
        return 'inspect'
    } else if (item.cents > 5000) {
        return 'approve'
    } else if (item.opened) {
        return 'seconds'
    }

    return 'shelf'
}

export function shelfFor(item: ParcelReturn): string {
    // @sin NestedTernary
    return item.opened ? (item.cents > 5000 ? 'inspection' : 'seconds') : 'new'
}

// @fixed NestedTernary
export function shelfOf(item: ParcelReturn): string {
    if (!item.opened) {
        return 'new'
    }

    return item.cents > 5000 ? 'inspection' : 'seconds'
}

export function flag(item: ParcelReturn, log: string[]): void {
    // @sin TernaryStatement
    item.opened ? log.push('opened') : log.push('sealed')
}

export function note(item: ParcelReturn, log: string[]): void {
    // @sin ShortCircuitStatement
    item.opened && log.push(item.reason)
}

export function refundNote(reason: string): string {
    // @sin ReassignedParameter
    reason = reason.trim().toLowerCase()

    return `refund: ${reason}`
}

export function refundTotals(batches: string[][], refunds: Map<string, number>): number {
    let total = 0
    for (const batch of batches) {
        for (const reason of batch) {
            switch (reason) {
                case 'damaged':
                    // @sin DeepNesting
                    while (refunds.has(reason)) {
                        total += refunds.get(reason) ?? 0
                        refunds.delete(reason)
                    }
                    break
            }
        }
    }

    return total
}

// @righteous DeepNesting
export function firstRefunded(batches: string[][], refunds: Map<string, number>): string {
    for (const batch of batches) {
        for (const reason of batch) {
            if (refunds.has(reason)) {
                if (reason !== '') {
                    return reason
                }
            }
        }
    }

    return ''
}

export interface ReturnLink {
    reason: string
    next: ReturnLink | null
}

export function reasonsAlong(first: ReturnLink): string[] {
    const reasons: string[] = []
    // @sin NonCountingFor
    for (let link: ReturnLink | null = first; link !== null; link = link.next) {
        reasons.push(link.reason)
    }

    return reasons
}

export function hasNote(note: string | null): boolean {
    // @sin CancelledCoalesce
    return (note ?? '') !== ''
}

// @fixed CancelledCoalesce
export function hasWrittenNote(note: string | null): boolean {
    return note !== null && note !== ''
}

export class RefundFailed extends Error {
    constructor(parcelId: string, options?: ErrorOptions) {
        super(`the refund of parcel ${parcelId} failed`, options)
    }
}

export function parsedReturnReason(raw: string): unknown {
    try {
        // @sin RawDecodedArrayReturn
        return JSON.parse(raw)
    }
    // @sin SwallowCatch
    catch {
        return null
    }
}

// @fixed SwallowCatch
export function returnReason(raw: string): unknown {
    // @sin RawDecodedArrayReturn
    return JSON.parse(raw)
}

export function refundedTotal(refunds: number[], ledger: { book(cents: number): void }): number {
    try {
        const total = refunds.reduce((sum, cents) => sum + cents, 0)
        ledger.book(total)

        return total
    }
    // @sin RethrownUnchanged
    catch (error) {
        throw error
    }
}

export function refundParcel(parcelId: string, gateway: { refund(id: string): void }): void {
    try {
        gateway.refund(parcelId)
    } catch (error) {
        // @sin WrappingWithoutCause
        throw new RefundFailed(parcelId)
    }
}

// @fixed WrappingWithoutCause
export function refundThroughGateway(parcelId: string, gateway: { refund(id: string): void }, attempts: number): void {
    for (let attempt = 1; attempt <= attempts; attempt++) {
        try {
            gateway.refund(parcelId)

            return
        } catch (error) {
            if (attempt === attempts) {
                throw new RefundFailed(parcelId, { cause: error })
            }
        }
    }
}

export function refundWindowDays(policy: { days?: number }): number {
    if (policy.days === undefined) {
        // @sin GenericException
        // @sin MessageAtThrow
        throw new Error('the refund policy names no window')
    }

    return policy.days
}
