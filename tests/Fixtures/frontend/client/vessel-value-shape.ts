let latestDock = ''

export function vesselTick(docks: string[]): string {
    if (docks.length === 0) return latestDock
    // @sin MutableStaticState
    latestDock = docks.join(' / ')
    return latestDock
}

export function vesselLine(
    // @sin ArrayBag
    fields: Record<string, string>,
): string {
    return `Moor at ${fields['wharf']} after ${fields['time']}`
}

function vesselKeys(
    // @sin ArrayBagParameter
    fields: Record<string, string>,
): string[] {
    const secured = Object.values(fields).every(code => code.startsWith('S-'))
    return secured ? Object.values(fields) : []
}

export function vesselNameKeys(): string[] {
    const first = vesselKeys({ gate: 'S-23', cabin: 'S-11' })
    const second = vesselKeys({ gate: 'S-19', cabin: 'S-17' })
    return first.concat(second)
}

export function vesselDefault(time: Date, berth: number) {
    // @sin ArrayReturnBag
    return { berth, departure: time.toISOString(), ready: berth > 0 }
}

class VesselShapeFields {
    constructor(readonly name: string, readonly city: string, readonly postcode: string, readonly stops: string[]) {}
}

// @sin FeatureEnvy
export function vesselDescription(fields: VesselShapeFields): string {
    let line = fields.name + fields.city + fields.postcode
    for (const stop of fields.stops) {
        line += ' / ' + stop
    }
    return line
}

// @sin KeyedLookupEnvy
export function vesselCarrier(carriers: Record<string, string>, fields: VesselShapeFields): string {
    return carriers[fields.postcode]
}

export class VesselShapePrinter {
    // @sin DataClump
    print(voyage: string, dock: string, vessel: string): string {
        return `${vessel.toUpperCase()} departs ${dock} on ${voyage}`
    }
}

export class VesselShapeValidator {
    // @sin DataClump
    check(voyage: string, dock: string, vessel: string): boolean {
        if (voyage.length < 3 || dock.length < 2) return false
        return vessel.charAt(0) === dock.charAt(0)
    }
}

export class VesselShapeState {
    private open = false
    private docked = false

    // @sin CoupledFields
    accept(open: boolean, docked: boolean): boolean {
        this.open = open
        this.docked = docked
        return this.open && !this.docked
    }

    // @sin BareStatePredicate
    opens(): boolean {
        return this.open || this.docked
    }

    // @sin NarratedCommand
    dispatches(): void {
        this.open = false
        this.docked = true
    }

    // @sin ScratchStateRestore
    probe(value: boolean): boolean {
        const saved = this.open
        this.open = value
        const moored = this.docked || this.open
        this.open = saved
        return moored
    }
}

export class VesselShapeValue {
    constructor(private label: string, private zone: string, private district: string) {}

    equals(other: VesselShapeValue): boolean {
        return this.label === other.label
    }

    // @sin MutableValueObject
    move(zone: string): void {
        this.zone = zone
    }

    // @sin HandRolledWither
    withLabel(label: string): VesselShapeValue {
        return new VesselShapeValue(label, this.zone, this.district)
    }
}

export class VesselShapeShelf {
    private tags: string[] = []

    allTags(): string[] {
        // @sin HandedOutCollection
        return this.tags
    }

    // @fixed HandedOutCollection
    snapshot(): string[] {
        return this.tags.slice()
    }
}

export class VesselShapeStarter {
    constructor(printer: VesselShapePrinter) {
        // @sin ConstructorSideEffect
        printer.print('Mill Road', 'Central', '10001')
    }
}
