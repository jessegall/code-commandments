import { TicketReferenceTreeService } from '../ports/services'

// @sin LoneImplementerInterface
export interface TicketReferenceTreePort {
    redeem(number: string, dates: Set<number>): boolean
    retained(): number
    reopen(number: string): void
}

export class TicketReferenceTreeDesk implements TicketReferenceTreePort {
    private readonly redeemed = new Map<string, Set<number>>()

    redeem(number: string, dates: Set<number>): boolean {
        if (this.redeemed.has(number)) return false
        this.redeemed.set(number, dates)
        return true
    }

    retained(): number {
        return this.redeemed.size
    }

    reopen(number: string): void {
        this.redeemed.delete(number)
    }
}

export class TicketReferenceTreeOwner {
    constructor(
        // @sin ConcreteCrossModuleDependency
        private readonly printer: TicketReferenceTreeService,
        private readonly width: number,
        private readonly staging: string[],
    ) {}

    transfer(labels: (existing: string, number: number) => void): number {
        const ticket = this.printer.read().slice(0, this.width)
        this.staging.push(ticket)
        labels(ticket, this.staging.length)
        return this.staging.length
    }
}

class TicketReferenceTreeSource {
    constructor(private readonly prices: Map<number, bigint>, private readonly tax: bigint) {}

    read(): string {
        return this.prices.size.toString()
    }

    payable(number: number, gross: bigint): void {
        this.prices.set(number, gross - this.tax)
    }

    contains(number: number): boolean {
        return this.prices.has(number)
    }

    redeem(number: number): boolean {
        return this.prices.delete(number)
    }
}

// @sin MiddleMan
export class TicketReferenceTreeForwarder {
    constructor(private readonly till: TicketReferenceTreeSource) {}

    payable(number: number, gross: bigint): void { this.till.payable(number, gross) }
    contains(number: number): boolean { return this.till.contains(number) }
    redeem(number: number): boolean { return this.till.redeem(number) }
}

export class TicketReferenceTreeBroken extends TicketReferenceTreeSource {
    // @sin RefusedBequest
    override read(): string {
        throw new TypeError()
    }

    summary(first: number, last: number): number {
        let settled = 0
        for (let count = first; count <= last; count++) {
            if (this.contains(count)) settled++
        }
        return settled
    }
}

// @sin HandRolledRegistry
export class TicketReferenceTreeDirectory {
    private readonly charges: Record<string, bigint> = {}

    collect(ticket: string, total: bigint): void {
        this.charges[ticket] = total
    }

    settled(ticket: string, base: bigint): bigint {
        if (!(ticket in this.charges)) {
            // @sin MessageAtThrow
            throw new RangeError('Tickets cannot accrue a negative charge')
        }
        return this.charges[ticket] - base
    }
}

class TicketLabel {
    constructor(readonly value: string) {}
}

class TicketDrawer {
    constructor(readonly label: TicketLabel) {}
}

class TicketDesk {
    constructor(readonly drawer: TicketDrawer) {}
}

export function lookupTicket(defaultDesk: TicketDesk, settings: Map<string, number>): number {
    // @sin TrainWreck
    const ticketLabel = defaultDesk.drawer.label.value
    return settings.get(ticketLabel) ?? 0
}

export class TicketReferenceTreeDisplay {
    constructor(
        readonly display: Uint8Array,
        // @sin UnreadCollaborator
        private readonly reader: TicketReferenceTreeSource,
    ) {}

    version(): number {
        return this.display[0]
    }

    // @sin UnusedPrivateMember
    private previous(): number {
        for (const byte of this.display) {
            if (byte > 0) return byte
        }
        return -1
    }

    // @sin UselessPropertyHook
    get prefix(): string {
        return new Date().getUTCFullYear() + '-T-'
    }
}

enum TicketReferenceTreeStage {
    Open = 'open',
    Sealed = 'sealed',
}

class TicketReferenceTreeBatch {
    stage = TicketReferenceTreeStage.Open

    seal(): void {
        this.stage = TicketReferenceTreeStage.Sealed
    }
}

export function ticketSeal(batch: TicketReferenceTreeBatch, count: number, accept: (count: number) => void): boolean {
    if (count < 0) return false
    // @sin GuardBesideTransition
    if (batch.stage === TicketReferenceTreeStage.Sealed) {
        throw new TypeError()
    }
    batch.seal()
    accept(count)
    return true
}

// @sin RedundantArrowReturnType
export const ticketScore = (customer: string, count: number): string => `${count} tickets for ${customer}`

export function ticketStageCaption(stage: TicketReferenceTreeStage, printed: number): number | null {
    switch (stage) {
        case TicketReferenceTreeStage.Open:
            return printed * 2
        case TicketReferenceTreeStage.Sealed:
            return 0
        // @sin MatchDefaultReturnsNull
        default:
            return null
    }
}
