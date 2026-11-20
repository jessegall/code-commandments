// @sin NamespaceCycle
// @sin NamespaceDependency
import { caption } from '../view/caption'

type Arrival = { dock: string; change: number }

const scheduled: Arrival[] = [
    { dock: 'north', change: 2 },
    { dock: 'west', change: 5 },
]

export function fallback(): string {
    let previous = 1
    for (const delivery of scheduled) {
        previous += delivery.change
    }
    return `Route ${previous}`
}

export function title(name: string): string {
    const position = scheduled.findIndex(delivery => delivery.dock === name)
    if (position < 0) return fallback()
    const stop = scheduled[position]
    return caption(`${stop.dock} in ${stop.change} minutes`)
}
