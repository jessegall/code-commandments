type Route = { stops: string[]; vehicle: string }

export class DispatchState {
    private draft?: Route
    private readonly routes = new Map<number, Route>()
    private vans = 0

    assign(bay: number, route: Route): void {
        this.routes.set(bay, route)
        this.draft = route
        this.vans++
    }

    stopCount(): number {
        // @sin MaskedInvariant
        return this.draft?.stops.length ?? 0
    }

    // @sin NullableCallback
    loadingCount(bay: number, announce?: (bay: number, count: number) => void): number {
        if (announce !== undefined) {
            announce(bay, this.vans)
        }
        return this.vans
    }

    findAtGate(bay: number, firstOpen: number): Route | undefined {
        if (bay < firstOpen) return undefined
        // @sin NullableRegistryLookup
        return this.routes.get(bay)
    }

    read(bytes: Uint8Array): unknown {
        const document = new TextDecoder().decode(bytes)
        // @sin RawDecodedArrayReturn
        return JSON.parse(document)
    }
}

class DispatchTruck {
    constructor(private readonly capacity: number, private readonly loaded: string[]) {}

    carries(weight: number): boolean { return weight <= this.capacity }
    emptyPlaces(total: number): number { return Math.max(0, total - this.loaded.length) }
    routeTo(bay: number): string { return `Bay ${bay} / ${this.loaded.join(', ')}` }
}

// @sin CapabilityProbeHub
export class DispatchProbe {
    constructor(private readonly carrier: unknown, private readonly requestedCapacity: number) {}

    hasRoom(): boolean {
        if (this.carrier instanceof DispatchTruck) { return this.carrier.carries(this.requestedCapacity) }
        return false
    }

    sparePlaces(seats: number): number {
        if (this.carrier instanceof DispatchTruck) {
            return this.carrier.emptyPlaces(seats)
        }
        return 0
    }

    loadingText(bay: number): string {
        if (this.carrier instanceof DispatchTruck) { return this.carrier.routeTo(bay) }
        return 'The truck is still on its way'
    }
}

// @sin BlankStringDefault
export function routeLabel(municipality: string = ''): string {
    if (municipality === '') return 'LOCAL DISPATCH'
    return municipality.trim().toUpperCase()
}

// @fixed BlankStringDefault
export function labelRequiredRouteCode(municipality: string): string {
    return municipality.trim().toUpperCase()
}

function queueRouteCode(gate: number, address: string): string {
    return gate + ' → ' + address
}

export function publishOptionalRouteCode(gate: number, route?: string): string {
    // @sin ManufacturedFakeFill
    return queueRouteCode(gate, route ?? '')
}

export function sendRequiredRouteCode(gate: number, route?: string): string | undefined {
    // @fixed ManufacturedFakeFill
    return route === undefined ? undefined : queueRouteCode(gate, route)
}

type Departure = { gate: number; ready: boolean }

export class Departures {
    constructor(private readonly board: Map<number, Departure>) {}

    // @sin DeNulledFinder
    departureAt(gate: number): Departure | undefined {
        // @sin NullableRegistryLookup
        return this.board.get(gate)
    }
}

export function boardingScreen(schedule: Departures, gate: number): string {
    return schedule.departureAt(gate)!.ready ? 'Board now' : 'Wait'
}

export function departureGate(schedule: Departures, gate: number): number {
    return schedule.departureAt(gate)!.gate
}

export function sendRouteLabels(values?: readonly string[]): string {
    let compiled = 'Dispatch:'
    // @sin CoalescedLoopSubject
    for (const value of values ?? []) {
        compiled += '\n' + value
    }
    return compiled
}

export function routeLabels(holds: string[], gate: number): string[] {
    // @sin ConditionalArraySpread
    return [`Gate ${gate}`, ...(gate > 0 ? holds : []), 'End of route']
}

export class RouteCode {
    constructor(readonly gate: number, readonly address: string) {}
}

export function emptyRouteCode(gate: number): RouteCode {
    // @sin PlaceholderFilledData
    return new RouteCode(gate, '')
}

// @fixed PlaceholderFilledData
export function permittedRouteCode(gate: number, address: string): RouteCode {
    return new RouteCode(gate, address)
}
