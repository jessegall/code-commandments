export enum InvoiceStage {
    Unposted = 'unposted',
    Settled = 'settled',
    Voided = 'voided',
}

export function active(stage: InvoiceStage): boolean {
    // @sin EnumCaseOrChain
    return stage === InvoiceStage.Unposted || stage === InvoiceStage.Settled
}

const activeStages = new Set([InvoiceStage.Unposted, InvoiceStage.Settled])

// @fixed EnumCaseOrChain
export function belongsToActiveStages(stage: InvoiceStage): boolean {
    return activeStages.has(stage)
}

export function accepted(value: string): boolean {
    // @sin InArrayMirrorsEnum
    return ['unposted', 'settled'].includes(value)
}

// @fixed InArrayMirrorsEnum
export function acceptsStage(value: InvoiceStage): boolean {
    return activeStages.has(value)
}

export function caption(value: string): string {
    // @sin StringMatchMirrorsEnum
    switch (value) {
        case 'unposted': return 'first stage'
        case 'settled': return 'second stage'
        case 'voided': return 'final stage'
        default: return 'unrecognized'
    }
}

// @righteous StringMatchMirrorsEnum
export function captionStage(stage: InvoiceStage): string {
    switch (stage) {
        case InvoiceStage.Unposted: return 'first stage'
        case InvoiceStage.Settled: return 'second stage'
        case InvoiceStage.Voided: return 'final stage'
    }
}

// @sin SideTableTrait
const assignedStages = [InvoiceStage.Settled, InvoiceStage.Voided]

export function assignedStage(stage: InvoiceStage): boolean {
    return assignedStages.includes(stage)
}

// @fixed SideTableTrait
const stageDetails = {
    [InvoiceStage.Unposted]: { assigned: false },
    [InvoiceStage.Settled]: { assigned: true },
    [InvoiceStage.Voided]: { assigned: true },
}

// @sin ConstClassEnum
export class InvoiceBand {
    static readonly Standard = 20
    static readonly Express = 64
}

export function standard(band: number): boolean {
    return band === InvoiceBand.Standard
}

class StandardInvoice {
    readonly amount = 20
}

class ExpressInvoice {
    readonly amount = 64
}

export function measure(value: StandardInvoice | ExpressInvoice): number {
    let reading: number
    // @sin TypeSwitch
    if (value instanceof StandardInvoice) {
        reading = value.amount + 1
    } else if (value instanceof ExpressInvoice) {
        reading = value.amount * 2
    } else {
        reading = 0
    }
    return reading
}
