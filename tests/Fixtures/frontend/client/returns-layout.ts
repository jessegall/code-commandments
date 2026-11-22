export class ReturnDesk {
    private count = 0
    private total = 0

    // @sin MemberOutOfOrder
    static readonly TimeLimit = 30

    receive(days: number, reference: string): boolean {
        if (days > ReturnDesk.TimeLimit) {
            this.deferred.push(reference)
            return false
        }
        this.count++
        this.total += days
        return true
    }

    // @sin MemberAfterMethod
    private readonly deferred: string[] = []

    archive(archive: (reference: string) => void): number {
        for (const reason of this.deferred) {
            archive(reason)
        }
        return this.count === 0 ? 0 : this.total / this.count
    }
}

// @sin PositionalTupleReturn
export function refundSummary(requested: number[], approved: number): [number, number, number] {
    const net = requested.reduce((total, value) => total + value, 0)
    const pending = Math.max(0, net - approved)
    // @sin PositionalTupleReturn
    return [net, pending, approved]
}

export function returnsCard(cents: number, reason: string): string {
    if (cents <= 0) return ''
    const refund = new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(cents / 100)
    // @sin AssembledTemplate
    const receipt = ['CUSTOMER COPY', refund, 'Refund reason', reason, 'Keep for your records'].join('\n')
    return receipt + '\n'
}
