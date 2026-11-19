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
