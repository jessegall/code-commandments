type Invoice = { memo: string }

export class BillingState {
    private draft?: Invoice
    private readonly records = new Map<string, Invoice>()

    prepare(supplied: Invoice): void {
        this.draft = supplied
    }

    draftValue(): string {
        // @sin MaskedInvariant
        return this.draft?.memo ?? ''
    }

    // @sin NullableCallback
    announce(notify?: () => void): void {
        notify?.()
    }

    find(key: string): Invoice | undefined {
        // @sin NullableRegistryLookup
        return this.records.get(key)
    }

    read(written: string): unknown {
        // @sin RawDecodedArrayReturn
        return JSON.parse(written)
    }
}

class BillingSource {
    settled(): boolean { return true }
    amount(): number { return 1 }
    describe(): string { return 'billing' }
}

// @sin CapabilityProbeHub
export class BillingProbe {
    constructor(private readonly provider: unknown) {}

    check(): boolean {
        if (this.provider instanceof BillingSource) { return this.provider.settled() }
        return false
    }

    rank(): number {
        if (this.provider instanceof BillingSource) { return this.provider.amount() }
        return 0
    }

    explain(): string {
        if (this.provider instanceof BillingSource) { return this.provider.describe() }
        return ''
    }
}

// @sin BlankStringDefault
export function labelInvoiceCode(name: string = ''): string {
    if (name === '') {
        return 'unassigned'
    }
    return name
}

// @fixed BlankStringDefault
export function labelRequiredInvoiceCode(name: string): string {
    return name.toUpperCase()
}

function publishInvoiceCode(label: string, write: (label: string) => void): void {
    write(label)
}

export function publishOptionalInvoiceCode(label: string | undefined, write: (label: string) => void): void {
    // @sin ManufacturedFakeFill
    publishInvoiceCode(label ?? '', write)
}

export function sendRequiredInvoiceCode(label: string | undefined, write: (label: string) => void): void {
    // @fixed ManufacturedFakeFill
    if (label !== undefined) {
        publishInvoiceCode(label, write)
    }
}

type Result = { value: string }

// @sin DeNulledFinder
// @sin ParamResolvedFromParam
function findInvoiceCode(bag: Record<string, Result>, key: string): Result | undefined {
    return bag[key]
}

export function viewInvoiceCode(bag: Record<string, Result>): string {
    return findInvoiceCode(bag, 'invoice')!.value
}

export function nextInvoiceCode(bag: Record<string, Result>): string {
    return findInvoiceCode(bag, 'PAID')!.value
}

export function sendInvoiceCodeLabels(values: readonly string[] | undefined, send: (value: string) => void): void {
    // @sin CoalescedLoopSubject
    for (const value of values ?? []) {
        send(value)
    }
}

export function listInvoiceCode(published: boolean): string[] {
    // @sin ConditionalArraySpread
    return [...(published ? ['PAID'] : []), 'draft']
}

export class InvoiceCode {
    constructor(readonly value: string) {}
}

export function newInvoiceCode(): InvoiceCode {
    // @sin PlaceholderFilledData
    return new InvoiceCode('')
}

// @fixed PlaceholderFilledData
export function namedInvoiceCode(value: string): InvoiceCode {
    return new InvoiceCode(value)
}
