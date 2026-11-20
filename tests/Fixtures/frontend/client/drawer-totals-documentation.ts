// @sin BloatedDocblock
/**
 * A balance includes the cash float.
 *
 * This second paragraph belongs with the code that plans the operation.
 */
export class DrawerTotals {
    readonly value = 0
}

// @righteous BloatedDocblock
/** A balance includes the cash float. */
export class DrawerTotalsDraft {}

// @sin StackedDocblock
/** A balance includes the cash float. */
/** This second doc comment has the same owner. */
export class DrawerTotalsReady {}

// @fixed StackedDocblock
/** A balance includes the cash float. */
export class DrawerTotalsPreview {}

// @sin DanglingDocReference
/** See {@link MissingTillReport} for the reporting shape. */
export function readDrawerTotals(): number {
    return 1
}

// @fixed DanglingDocReference
/** See {@link DrawerTotals} for the reporting shape. */
export function nextDrawerTotals(): number {
    return 2
}

// @sin CeremonyDocblock
/**
 * @param {number} value
 * @returns {number}
 */
export function drawerAmount(value: number): number {
    return value
}

// @fixed CeremonyDocblock
export function drawerChecked(value: number): number {
    return Math.max(0, value)
}

export function drawerBalance(drawer: { balance: number }): number {
    // @sin ArchaeologyComment
    // Previously this calculation used a cached value.
    const amount = drawer.balance
    // @sin NegativeSpaceComment
    // The currency is deliberately not rounded here.
    const calculated = Math.abs(amount)
    // @sin RestatedComment
    // Return calculated amount.
    return calculated + amount
}

// @fixed ArchaeologyComment
// @fixed NegativeSpaceComment
// @fixed RestatedComment
export function drawerForDisplay(amount: number): number {
    return Math.abs(amount)
}
