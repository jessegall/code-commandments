// @sin BloatedDocblock
/**
 * Stocks are counted by a named location.
 *
 * The stock ledger visits each aisle in the order sent by the main warehouse.
 */
export interface StockLevel {
    counted(): number
    skipped(): number
    barcode: string
}

// @righteous BloatedDocblock
/** The estimate attached to an inventory form. */
export class StockLevelDraft {
    constructor(readonly scan: string, readonly expected: number) {}
}

// @sin StackedDocblock
/** A row headed to the inventory screen. */
/** Amounts visible from the receiving entry. */
export class StockLevelReady {
    constructor(readonly approved: Map<string, number>) {}

    units(): number {
        return Array.from(this.approved.values()).reduce((total, units) => total + units, 0)
    }
}

// @fixed StackedDocblock
/** A temporary view of a count recorded at the desk. */
export type StockLevelPreview = { written: string; units: number }

// @sin DanglingDocReference
/** Recheck the bounds described by {@link MissingStockLedger} before reserving. */
export function readStockLevel(level: StockLevel, limits: Map<string, number>): boolean {
    const known = limits.get(level.barcode)
    if (known === undefined) return false
    return level.counted() - level.skipped() >= known
}

// @fixed DanglingDocReference
/** Reserve the net count reported by {@link StockLevel}. */
export function stockForSale(level: StockLevel): number {
    return level.counted() - level.skipped()
}

// @sin CeremonyDocblock
/**
 * @param {number} visible
 * @param {number} lost
 * @returns {number}
 */
export function reserveStock(visible: number, lost: number): number {
    const available = visible - lost
    if (available > 50) return 50
    return available
}

// @fixed CeremonyDocblock
export function expectedStock(visible: number, lost: number): number {
    return Math.min(50, visible - lost)
}

export function stockBalance(estimated: Map<number, number>, key: number): number {
    // @sin ArchaeologyComment
    // Previously we used the cellar tally for both warehouses.
    if (estimated.has(key)) {
        return expectedStock(estimated.get(key)!, 0)
    }
    // @sin NegativeSpaceComment
    // An empty stock adjustment is not a typo.
    const counted = estimated.size
    // @sin RestatedComment
    // Return counted.
    return counted
}

// @fixed ArchaeologyComment
// @fixed NegativeSpaceComment
// @fixed RestatedComment
export function stockForDisplay(estimated: Map<number, number>, key: number): number {
    const units = estimated.get(key)
    if (units !== undefined) return expectedStock(units, 0)
    return estimated.size
}
