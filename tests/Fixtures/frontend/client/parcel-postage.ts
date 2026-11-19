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
        throw new Error('no such zone')
    } else {
        return `zone ${parcel.zone}`
    }
}

// @fixed RedundantElse
export function zoneLabel(parcel: Parcel): string {
    if (parcel.zone > 4) {
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
