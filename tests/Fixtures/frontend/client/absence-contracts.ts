type Notice = { title: string; count: number }

// @righteous PlaceholderFilledData
export function notice(title: string, count: number): Notice {
    return { title, count }
}

// @righteous BlankStringDefault
export function bracketLabel(label = ''): string {
    return '[' + label + ']'
}

function optionalNotice(title: string): Notice | undefined {
    return title.length > 0 ? notice(title, title.length) : undefined
}

// @righteous DeNulledFinder
export function noticeCount(title: string): number {
    const found = optionalNotice(title)
    return found ? found.count : 0
}

// @righteous ManufacturedFakeFill
export function printNotice(item: Notice): string {
    return notice(item.title, item.count).title
}

// @righteous CoalescedLoopSubject
export function countNotices(notices?: readonly Notice[]): number {
    if (!notices) {
        return 0
    }
    let total = 0
    for (const item of notices) {
        total += item.count
    }
    return total
}

// @righteous ConditionalArraySpread
export function combineNotices(left: readonly Notice[], right: readonly Notice[]): Notice[] {
    return [...left, ...right]
}

// @righteous NullableRegistryLookup
export function getNotice(index: ReadonlyMap<string, Notice>, key: string): Notice | undefined {
    return index.get(key)
}

export class PendingNotice {
    private current?: Notice

    // @righteous MaskedInvariant
    title(): string {
        return this.current?.title ?? 'Pending'
    }
}

function ignoreDelivery(_count: number): void {}

// @righteous NullableCallback
export function sendNotice(item: Notice, delivered: (count: number) => void = ignoreDelivery): void {
    delivered(item.count)
}

// @righteous RawDecodedArrayReturn
export function restoreNotice(text: string): Notice {
    const found: unknown = JSON.parse(text)
    if (typeof found === 'string') {
        return notice(found, 1)
    }
    // @sin MessageAtThrow
    throw new TypeError('Expected a notice title')
}

// @righteous CapabilityProbeHub
export function receiveNotice(value: unknown): boolean {
    return value instanceof PendingNotice
}

// @righteous SwallowCatch
export function sendOrRetry(action: () => void, retry: () => void): void {
    try {
        action()
    } catch {
        retry()
    }
}

// @righteous WrappingWithoutCause
export function sendSafely(action: () => void): void {
    try {
        action()
    } catch (cause) {
        // @sin GenericException
        // @sin MessageAtThrow
        throw new Error('The notice did not send', { cause })
    }
}
