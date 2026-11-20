// Postage for a parcel leaving the warehouse, by the zone it travels to and the weight it carries.
export interface Parcel {
    zone: number
    grams: number
    labels: string[]
}

export function postage(parcel: Parcel): number {
    let cents = 0
    // @sin SubjectLadder
    if (parcel.zone == 1) {
        cents = 450
    } else if (parcel.zone == 2) {
        cents = 700
    } else if (parcel.zone == 3) {
        cents = 1150
    } else if (parcel.zone == 4) {
        cents = 1900
    } else {
        cents = 2500
    }
    if (parcel.grams > 2000) {
        cents += Math.floor(parcel.grams / 10)
    }

    return cents
}
// @sin FeatureEnvy
export function label(parcel: Parcel): Parcel {
    // @sin DuplicatedBranchTail
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
        parcel.zone = Math.max(parcel.zone, 1)
    } else if (parcel.grams > 500) {
        parcel.labels.push('medium')
        parcel.zone = Math.max(parcel.zone, 1)
    } else {
        parcel.zone = Math.max(parcel.zone, 1)
    }

    return parcel
}
// @sin FeatureEnvy
// @fixed DuplicatedBranchTail
export function labelOnce(parcel: Parcel): Parcel {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
    } else if (parcel.grams > 500) {
        parcel.labels.push('medium')
    }
    parcel.zone = Math.max(parcel.zone, 1)

    return parcel
}

export function sorted(parcels: Parcel[]): number {
    let taken = 0
    for (const parcel of parcels) {
        if (parcel.grams > 30000) {
            continue
            // @sin UnreachableStatement
            taken--
        }
        taken++
    }

    return taken
}

export function zoneName(parcel: Parcel): string {
    // @sin RedundantElse
    if (parcel.zone > 4) {
        // @sin GenericException
        // @sin MessageAtThrow
        throw new Error('no such zone')
    } else {
        return `zone ${parcel.zone}`
    }
}

// @fixed RedundantElse
export function zoneLabel(parcel: Parcel): string {
    if (parcel.zone > 4) {
        // @sin GenericException
        // @sin MessageAtThrow
        throw new Error('no such zone')
    }

    return `zone ${parcel.zone}`
}

export function weighAll(byZone: Record<string, Parcel>): number {
    let grams = 0
    for (const zone in byZone) {
        // @sin LoopInvertedGuard
        if (byZone[zone].labels.length > 0) {
            grams += byZone[zone].grams
            byZone[zone].labels.push('weighed')
        }
    }

    return grams
}
// @sin FeatureEnvy
// @righteous DuplicatedBranchTail
export function weigh(parcel: Parcel): Parcel {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
    }
    parcel.zone = Math.max(parcel.zone, 1)

    return parcel
}

export function band(parcel: Parcel): string {
    const heavy = parcel.grams > 2000
    // @sin NestedTernary
    const label = parcel.zone > 3 ? 'far' : heavy ? 'near-heavy' : 'near'

    return label
}

export function stamp(parcel: Parcel): void {
    // @sin TernaryStatement
    parcel.grams > 2000 ? parcel.labels.push('heavy') : parcel.labels.splice(0)
}

// @fixed TernaryStatement
export function stampOnce(parcel: Parcel): void {
    if (parcel.grams > 2000) {
        parcel.labels.push('heavy')
    } else {
        parcel.labels.splice(0)
    }
}

export function relabel(parcel: Parcel): void {
    // @sin ShortCircuitStatement
    parcel.labels.length > 0 || parcel.labels.push('unlabelled')
}

// @fixed ShortCircuitStatement
export function relabelEmpty(parcel: Parcel): void {
    if (parcel.labels.length === 0) {
        parcel.labels.push('unlabelled')
    }
}

export function postageCents(grams: number, zone: number): number {
    // @sin ReassignedParameter
    grams = Math.max(grams, 100)

    return grams * zone
}

// @fixed ReassignedParameter
export function billedPostageCents(grams: number, zone: number): number {
    const billed = Math.max(grams, 100)

    return billed * zone
}

// @righteous ReassignedParameter
export function rezone(parcel: Parcel, zone: number): Parcel {
    parcel.zone = Math.max(zone, 1)

    return parcel
}
// @sin FeatureEnvy
export function sortIntoBins(parcels: Parcel[], bins: Map<number, Parcel[]>): void {
    for (const parcel of parcels) {
        if (parcel.grams > 0) {
            if (bins.has(parcel.zone)) {
                // @sin DeepNesting
                if (parcel.labels.length > 0) {
                    bins.get(parcel.zone)?.push(parcel)
                }
            }
        }
    }
}
// @sin FeatureEnvy
// @fixed DeepNesting
export function sortLabelledIntoBins(parcels: Parcel[], bins: Map<number, Parcel[]>): void {
    for (const parcel of parcels) {
        if (parcel.grams <= 0 || parcel.labels.length === 0) {
            continue
        }
        bins.get(parcel.zone)?.push(parcel)
    }
}

export function zonesHopped(start: number, hops: Map<number, number>): number[] {
    const zones: number[] = []
    // @sin NonCountingFor
    for (let zone = start; hops.has(zone); zone = hops.get(zone) ?? 0) {
        zones.push(zone)
    }

    return zones
}

// @righteous NonCountingFor
export function everyOtherZone(last: number): number[] {
    const zones: number[] = []
    for (let zone = 1; zone <= last; zone += 2) {
        zones.push(zone)
    }

    return zones
}

export function unweighed(parcel: { grams?: number }): boolean {
    // @sin CancelledCoalesce
    if ((parcel.grams ?? 0) === 0) {
        return true
    }

    return false
}

// @righteous CancelledCoalesce
export function weightOrDefault(parcel: { grams?: number }, fallback: number): number {
    return parcel.grams ?? fallback
}

export class PostageUnknown extends Error {
    constructor(zone: string, options?: ErrorOptions) {
        super(`no postage is known for zone ${zone}`, options)
    }
}

export function weighedParcels(parcels: string[], scale: { weigh(parcel: string): number }): number[] {
    const weights: number[] = []
    for (const parcel of parcels) {
        try {
            weights.push(scale.weigh(parcel))
        }
        // @sin SwallowCatch
        catch {
            continue
        }
    }

    return weights
}

export function stampedLabel(parcel: string, printer: { print(text: string): string; release(): void }): string {
    try {
        return printer.print(parcel)
    }
    // @sin RethrownUnchanged
    catch (failure) {
        throw failure
    } finally {
        printer.release()
    }
}

// @righteous RethrownUnchanged
export function printedManifest(lines: string[], printer: { print(text: string): string; reset(): void }): string {
    try {
        return printer.print(lines.join('\n'))
    } catch (failure) {
        printer.reset()
        throw failure
    }
}

export function zoneRate(zone: string, lookup: (zone: string) => number): number {
    try {
        return lookup(zone)
    } catch {
        // @sin WrappingWithoutCause
        throw new PostageUnknown(zone)
    }
}

export function postageFor(grams: number): number {
    if (grams <= 0) {
        // @sin GenericException
        // @sin MessageAtThrow
        throw new Error(`a parcel of ${grams} grams cannot be posted`)
    }

    return grams * 2
}

// @righteous GenericException
// @righteous MessageAtThrow
export function postageInZone(zone: string, rates: Record<string, number>): number {
    if (!(zone in rates)) {
        throw new PostageUnknown(zone)
    }

    return rates[zone]
}
