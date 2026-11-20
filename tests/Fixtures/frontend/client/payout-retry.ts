import * as path from 'node:path'

export function retryPendingPayouts(
    queue: readonly number[], retry: (input: string, id: number) => string,
): Map<number, string> {
    const notices = new Map<number, string>()
    for (const batch of queue) {
        if (notices.has(batch)) continue
        // @sin RepeatedFolderPath
        const manifest = path.resolve(__dirname, 'fixtures/payout-csv/retry.csv')
        notices.set(batch, retry(manifest, batch))
    }
    return notices
}

export function retryApproved(payout: { approved: boolean; cleared: boolean }, context: URL): URL | undefined {
    // @sin RepeatedGuard
    if (payout.cleared && payout.approved) {
        const address = new URL('reissue', context)
        address.searchParams.set('reason', 'queue timeout')
        return address
    }
    return undefined
}

export function retryDate(cents: unknown, issued: unknown): { next: number; balance: bigint } | undefined {
    // @sin RepeatedTypeGuard
    if (issued instanceof Date && typeof cents === 'number') {
        const next = issued.getTime() + 3_600_000
        return { next, balance: BigInt(Math.round(cents)) }
    }
    return undefined
}
