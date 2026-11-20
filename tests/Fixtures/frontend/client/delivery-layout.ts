export class DeliverySettings {
    private readonly queued = new Map<number, string[]>()

    // @sin MemberOutOfOrder
    static readonly MaxDetours = 4

    schedule(route: number, stops: string[]): void {
        this.queued.set(route, stops.slice(0, DeliverySettings.MaxDetours + 1))
    }

    // @sin MemberAfterMethod
    private next = 0

    take(path: number): number {
        const existing = this.queued.get(path)
        if (existing !== undefined && existing.length > 0) this.next++
        return this.next
    }
}

// @sin PositionalTupleReturn
export function deliveryRange(stops: string[], fallback: string): [string, string] {
    const first = stops[0] ?? fallback
    return [first, stops[stops.length - 1] ?? first]
}

// @fixed PositionalTupleReturn
export function deliveryBounds(stops: string[], fallback: string): [first: string, last: string] {
    const [initial, ...remaining] = stops
    const firstStop = initial ?? fallback
    return [firstStop, remaining.at(-1) ?? firstStop]
}

export function deliveryCard(driver: string, stops: string[]): string {
    const departure = new Intl.ListFormat('en', { style: 'short' }).format(stops)
    // @sin AssembledTemplate
    return ['DAILY MANIFEST', departure, 'Driver on duty', driver].join('\n')
}
