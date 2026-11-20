import { join } from 'node:path'

export function payoutArchive(batch: string): { batch: string; output: string } {
    // @sin RepeatedFolderPath
    const output = join(__dirname, 'fixtures', 'payout-csv', 'archive.csv')

    return { batch, output }
}

export const payoutCsvFolder = join(__dirname, 'fixtures', 'payout-csv')

// @fixed RepeatedFolderPath
export function namedPayoutArchive(batch: string): { batch: string; output: string } {
    return { batch, output: join(payoutCsvFolder, 'archive.csv') }
}

export function payoutReady(payout: { approved: boolean; cleared: boolean }): boolean {
    // @sin RepeatedGuard
    return payout.approved && payout.cleared
}

export function payoutDate(cents: unknown, issued: unknown): Date | undefined {
    // @sin RepeatedTypeGuard
    if (typeof cents === 'number' && issued instanceof Date) {
        return issued
    }

    return undefined
}

function canPay(payout: { approved: boolean; cleared: boolean }): boolean {
    const ready = payout.approved && payout.cleared
    return ready
}

// @fixed RepeatedGuard
export function archiveReady(payout: { approved: boolean; cleared: boolean }): boolean {
    return canPay(payout)
}

// @fixed RepeatedTypeGuard
export function validPayoutDate(cents: unknown, issued: unknown): Date | undefined {
    const dateAndAmount = typeof cents === 'number' && issued instanceof Date
    if (dateAndAmount) {
        return issued
    }

    return undefined
}
