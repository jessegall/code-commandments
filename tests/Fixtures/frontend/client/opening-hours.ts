import { OpeningWindow } from './opening-window'

export function openingStatus(opens: number, closes: number, hour: number): string {
    if (OpeningWindow.between(opens, closes).covers(hour)) {
        return 'open'
    }

    // @sin RepeatedStaticFactory
    return `closed, open ${OpeningWindow.between(opens, closes).hours()} hours a day`
}

// @fixed RepeatedStaticFactory
export function openingStatusOnce(opens: number, closes: number, hour: number): string {
    const window = OpeningWindow.between(opens, closes)
    if (window.covers(hour)) {
        return 'open'
    }

    return `closed, open ${window.hours()} hours a day`
}
