export function choosePickup(order: { packed: boolean }, window: { open: boolean; minutes: number }): string {
    let message = 'collect tomorrow'
    // @sin UnnamedCondition
    if (order.packed && window.open && window.minutes > 15) {
        message = 'collect today'
    }

    return message
}

// @fixed UnnamedCondition
export function pickupMessage(order: { packed: boolean }, window: { open: boolean; minutes: number }): string {
    const canCollectToday = order.packed && window.open && window.minutes > 15

    return canCollectToday ? 'collect today' : 'collect tomorrow'
}
