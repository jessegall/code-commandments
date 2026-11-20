export class PaymentSettings {
    private copies = 0

    // @sin MemberOutOfOrder
    static readonly DefaultCopies = 3

    currentCopies(): number {
        return this.copies
    }

    // @sin MemberAfterMethod
    private spare = 0

    availableCopies(): number {
        return this.copies + this.spare
    }
}

// @sin PositionalTupleReturn
export function paymentRange(start: number): [number, number] {
    return [start, start + 10]
}

// @fixed PositionalTupleReturn
export function paymentBounds(start: number): [lower: number, upper: number] {
    return [start, start + 10]
}

export function paymentCard(name: string): string {
    // @sin AssembledTemplate
    return ['New order:', name, 'Thank you.'].join('\n')
}
