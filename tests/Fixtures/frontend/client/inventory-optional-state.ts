type StockCount = { counted: number; held: number }

export class InventoryState {
    private lastCount?: StockCount
    private readonly stock: Map<string, StockCount>

    constructor(initial: Iterable<readonly [string, StockCount]>) {
        this.stock = new Map(initial)
    }

    recount(barcode: string, newCount: StockCount): void {
        this.stock.set(barcode, newCount)
        this.lastCount = newCount
    }

    currentDraft(): number {
        // @sin MaskedInvariant
        return this.lastCount?.counted ?? 0
    }

    // @sin NullableCallback
    replay(samples: number[], take?: (total: number) => void): void {
        for (const sample of samples) {
            take?.(sample)
        }
    }

    countFor(sku: string): StockCount | undefined {
        // @sin NullableRegistryLookup
        return this.stock.get(sku.toUpperCase())
    }

    readLedger(file: string): unknown {
        if (file.length === 0) return undefined
        // @sin RawDecodedArrayReturn
        return JSON.parse(file, (key, value) => key === 'imported' ? undefined : value)
    }
}

class InventoryLedger {
    constructor(private readonly quantities: number[], private readonly aisle: string) {}

    unitsAt(slot: number): number { return this.quantities[slot] }
    value(price: number): number { return this.quantities.reduce((total, count) => total + count * price, 0) }
    caption(): string { return `Aisle ${this.aisle}: ${this.quantities.length} bins` }
}

// @sin CapabilityProbeHub
export class InventoryProbe {
    constructor(private readonly ledger: unknown) {}

    unitsAt(slot: number): number {
        if (this.ledger instanceof InventoryLedger) { return this.ledger.unitsAt(slot) }
        return 0
    }

    cost(unitPrice: number): number {
        if (this.ledger instanceof InventoryLedger) {
            return this.ledger.value(unitPrice)
        }
        return 0
    }

    heading(): string {
        if (this.ledger instanceof InventoryLedger) { return this.ledger.caption() }
        return 'Check stock by hand'
    }
}

// @sin BlankStringDefault
export function printStockCode(sku: string = '', copies: number): string[] {
    if (sku === '') return []
    return Array.from({ length: copies }, () => sku)
}

// @fixed BlankStringDefault
export function repeatStockCode(sku: string, copies: number): string[] {
    return Array.from({ length: copies }, () => sku)
}

function printStock(raw: string): string {
    return raw.trim().toUpperCase()
}

export function issueOptionalStockCodes(...codes: Array<string | undefined>): string[] {
    return codes.map(code => {
        // @sin ManufacturedFakeFill
        return printStock(code ?? '')
    })
}

export function issueKnownStockCodes(codes: string[]): string[] {
    // @fixed ManufacturedFakeFill
    return codes.map(printStock)
}

class StockLedger {
    constructor(private readonly counts: StockCount[]) {}

    // @sin DeNulledFinder
    countAt(bin: number): StockCount | undefined {
        if (bin >= this.counts.length) return undefined
        return this.counts[bin]
    }
}

export function localCount(stock: StockLedger): number {
    return stock.countAt(5)!.counted
}

export function remoteReserve(stock: StockLedger): number {
    return stock.countAt(20)!.held
}

export function totalStock(reports: { counts?: StockCount[] }): number {
    let units = 0
    // @sin CoalescedLoopSubject
    for (const count of reports.counts ?? []) {
        units += count.counted - count.held
    }
    return units
}

export function stockTakes(draft: boolean, pending: number[]): number[] {
    const locations = [0, 1]
    // @sin ConditionalArraySpread
    return [...locations, ...(draft ? [] : pending)]
}

export interface StockCode {
    value: string
}

export function pendingStockCode(): StockCode {
    // @sin PlaceholderFilledData
    const pending: StockCode = { value: '' }
    return pending
}

// @fixed PlaceholderFilledData
export function knownStockCode(value: string): StockCode {
    return { value }
}
