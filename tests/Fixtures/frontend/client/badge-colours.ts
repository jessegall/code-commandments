// Two lookup tables written as a switch: each maps a status to a colour, and they differ only in
// their DATA. There is no procedure to hoist — merging them would only move the rows.

// @righteous NearDuplicateFunction
export function orderColour(status: string): string {
    switch (status) {
        case 'paid':
            return 'green'
        case 'sent':
            return 'blue'
        case 'lost':
            return 'red'
        case 'held':
            return 'amber'
        default:
            return 'grey'
    }
}

// @righteous NearDuplicateFunction
export function stockColour(level: string): string {
    switch (level) {
        case 'plenty':
            return 'green'
        case 'low':
            return 'amber'
        case 'out':
            return 'red'
        case 'incoming':
            return 'blue'
        default:
            return 'grey'
    }
}
