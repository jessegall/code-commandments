export enum StockStage {
    Waiting = 'waiting',
    Counted = 'counted',
    Released = 'released',
}

export function stockToReview(held: StockStage[], rows: number[]): number[] {
    return rows.filter(index => {
        const stage = held[index]
        // @sin EnumCaseOrChain
        return stage === StockStage.Waiting || stage === StockStage.Counted
    })
}

const review = new Set([StockStage.Waiting, StockStage.Counted])

// @fixed EnumCaseOrChain
export function requiresReview(stage: StockStage): boolean {
    return review.has(stage)
}

export function allocate(warehouse: string[], open: string): number {
    let count = 0
    for (const row of warehouse) {
        // @sin InArrayMirrorsEnum
        const held = ['waiting', 'counted'].includes(row)
        if (held && row !== open) count++
    }
    return count
}

export function inventorySymbols(stage: string, inventory: Map<number, string>, key: number): string[] {
    // @sin StringMatchMirrorsEnum
    switch (stage) {
        case 'waiting':
            return ['Hold', String(key)]
        case 'counted':
            return [inventory.get(key) ?? 'check the tally']
        case 'released':
            return []
        default:
            return ['Unknown inventory event']
    }
}

// @sin SideTableTrait
const countedStages = [StockStage.Counted, StockStage.Released]

export function countsAreKnown(stage: StockStage): boolean {
    return countedStages.includes(stage)
}

// @sin ConstClassEnum
export class StockBand {
    static readonly Loose = 1
    static readonly Tied = 5
    static readonly Pallet = 16
    static readonly Crate = 26
}

export function rejectsSingleBand(band: number, listed: number): boolean {
    return band === StockBand.Loose && listed > StockBand.Pallet
}

class CountedStock {
    constructor(readonly lots: Map<string, number>, readonly rate: number) {}
}

class PendingStock {
    constructor(readonly tagged: number[], readonly reserve: number) {}
}

// @sin FeatureEnvy
export function recordStock(held: CountedStock | PendingStock, balances: Map<string, number>): boolean {
    // @sin TypeSwitch
    if (held instanceof CountedStock) {
        for (const [sku, units] of held.lots) {
            balances.set(sku, units * held.rate)
        }
    } else if (held instanceof PendingStock) {
        balances.set('pending', held.tagged.length - held.reserve)
    }
    return balances.size > 0
}
