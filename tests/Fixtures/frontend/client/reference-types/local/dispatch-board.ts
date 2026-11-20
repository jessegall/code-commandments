import { TicketReferenceTreeService as Register } from '../ports/services'

// @sin LoneImplementerInterface
export interface CounterDispatch {
    confirm(reference: bigint, address: URL): void
    completed(since: Date): bigint[]
}

export class CounterExport implements CounterDispatch {
    private readonly sent = new Map<bigint, number>()

    confirm(reference: bigint, address: URL): void {
        this.sent.set(reference, Number(address.port))
    }

    completed(since: Date): bigint[] {
        return Array.from(this.sent).filter(([, opened]) => opened > since.valueOf()).map(([reference]) => reference)
    }
}

export class DispatchManifest {
    private readonly keys: Set<string>
    private readonly register: Register

    constructor(
        issued: string[],
        // @sin ConcreteCrossModuleDependency
        printer: Register,
        wanted: number,
    ) {
        this.keys = new Set(issued.slice(0, wanted))
        this.register = printer
    }

    manifest(room: string): string {
        return room + ' / ' + this.register.read() + ' / ' + Array.from(this.keys).join(', ')
    }
}

export class CounterList {
    constructor(private readonly received: Uint8Array[]) {}

    pickup(): Uint8Array | undefined {
        return this.received.shift()
    }

    defer(route: Uint8Array): void {
        this.received.unshift(route)
    }
}

// @sin MiddleMan
export class CounterRelay {
    constructor(private readonly inbox: CounterList) {}

    pickup(): Uint8Array | undefined {
        return this.inbox.pickup()
    }

    defer(route: Uint8Array): void {
        this.inbox.defer(route)
    }
}

export class LockedCounter extends CounterList {
    // @sin RefusedBequest
    override pickup(): Uint8Array | undefined {
        throw new RangeError()
    }

    restore(overdue: readonly Uint8Array[], sign: (placed: number) => void): void {
        for (const ticket of overdue) {
            this.defer(ticket)
            sign(ticket.byteLength)
        }
    }
}

// @sin HandRolledRegistry
export class AssignedGates {
    private readonly loading: Record<number, Date> = {}

    book(number: number, issued: Date): void {
        this.loading[number] = issued
    }

    clear(number: number, out: (hours: number) => void): void {
        if (this.loading[number] === undefined) {
            throw new RangeError()
        }
        out(this.loading[number].getUTCHours())
        delete this.loading[number]
    }
}
