export enum ParcelStage {
    Draft = 'draft',
    Ready = 'ready',
    Sent = 'sent',
}

export function parcelsForPickup(stage: ParcelStage, regular: string[], late: string[]): string[] {
    // @sin EnumCaseOrChain
    if (stage === ParcelStage.Draft || stage === ParcelStage.Ready) {
        return regular.concat(late)
    }
    return late
}

const activeStages = new Set([ParcelStage.Draft, ParcelStage.Ready])

// @fixed EnumCaseOrChain
export function belongsToActiveStages(stage: ParcelStage): boolean {
    return activeStages.has(stage)
}

export function addToManifest(
    submitted: string, rows: Map<string, number>, entry: string, supplier: (id: string) => number,
): void {
    // @sin InArrayMirrorsEnum
    if (['draft', 'ready'].includes(submitted)) {
        rows.set(entry, supplier(entry))
    }
}

// @fixed InArrayMirrorsEnum
export function acceptsStage(value: ParcelStage): boolean {
    return activeStages.has(value)
}

export function announceParcel(stage: string, code: string, notice: (text: string) => void): void {
    // @sin StringMatchMirrorsEnum
    switch (stage) {
        case 'draft':
            notice(code + ' is being prepared')
            break
        case 'ready':
            notice('Collect parcel ' + code)
            break
        case 'sent':
            notice('Parcel ' + code + ' is on its way')
            break
    }
}

// @righteous StringMatchMirrorsEnum
export function stageCanShip(stage: ParcelStage): boolean {
    return stage === ParcelStage.Ready
}

// @sin SideTableTrait
const assignedStages = [ParcelStage.Ready, ParcelStage.Sent]

export function assignedStage(stage: ParcelStage): boolean {
    return assignedStages.includes(stage)
}

// @fixed SideTableTrait
const stageDetails = {
    [ParcelStage.Draft]: { assigned: false },
    [ParcelStage.Ready]: { assigned: true },
    [ParcelStage.Sent]: { assigned: true },
}

// @sin ConstClassEnum
export class ParcelBand {
    static readonly Local = 'local'
    static readonly International = 'international'
    static readonly Remote = 'remote'
}

export function deliveryZone(band: string, depot: number): string {
    if (band === ParcelBand.International) {
        return 'EXPORT/' + depot.toString()
    }
    return 'DEPOT/' + depot.toString()
}

export class StandardParcel {
    constructor(readonly address: string[], readonly code: string) {}
}

export class ExpressParcel {
    constructor(readonly carrier: string, readonly account: string, readonly cutOff: Date) {}
}

// @sin FeatureEnvy
export function routeManifest(value: StandardParcel | ExpressParcel, office: string): string[] {
    let label: string[] = []
    // @sin TypeSwitch
    if (value instanceof StandardParcel) {
        label = [office, value.code].concat(value.address)
    } else if (value instanceof ExpressParcel) {
        label = [value.carrier, value.account, value.cutOff.toISOString()]
    }
    return label
}
