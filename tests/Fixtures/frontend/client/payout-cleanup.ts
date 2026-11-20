import { join as joinPath } from 'path'

export function purgeInvalidPayouts(
    batches: Map<string, boolean>, remove: (filename: string, affected: string[]) => void,
): number {
    const expired: string[] = []
    for (const [batch, current] of batches) {
        if (!current) expired.push(batch)
    }
    if (expired.length === 0) return 0

    // @sin RepeatedFolderPath
    const source = joinPath(__dirname, 'fixtures', 'payout-csv', 'invalid.csv')
    remove(source, expired)
    return expired.length
}

// @righteous RepeatedFolderPath
export function purgeDrafts(remove: (filename: string) => void): void {
    remove(joinPath(__dirname, 'preview', 'draft.csv'))
}

export function purgeApproved(
    payout: { approved: boolean; cleared: boolean }, batch: string, audit: Map<string, number>,
): void {
    // @sin RepeatedGuard
    if (payout.approved && payout.cleared) {
        const previous = audit.get(batch) ?? 0
        audit.set(batch, previous + 1)
    }
}

export function purgeDate(cents: unknown, issued: unknown, output: (amount: number, key: string) => void): number {
    // @sin RepeatedTypeGuard
    if (typeof cents === 'number' && issued instanceof Date) {
        output(cents / 100, issued.toISOString())
        return cents
    }
    return 0
}

// @righteous RepeatedTypeGuard
export function dateOnly(issued: unknown): boolean {
    return issued instanceof Date
}
