const notices: (typeof ParcelNotice)[] = []

abstract class ParcelNotice {
    abstract heading: string
}

class DelayedNotice extends ParcelNotice {
    heading = 'Delayed'
    static { notices.push(this) }
}
class DeliveredNotice extends ParcelNotice {
    heading = 'Delivered'
    static { notices.push(this) }
}
class PickupNotice extends ParcelNotice {
    heading = 'Ready for pickup'
    static { notices.push(this) }
}
class ReturnNotice extends ParcelNotice {
    heading = 'Returned'
    static { notices.push(this) }
}
class DispatchNotice extends ParcelNotice {
    heading = 'Dispatched'
    static { notices.push(this) }
}

export function parcelNoticeTypes(): (typeof ParcelNotice)[] {
    // @sin ListMirrorsFolder
    return [DelayedNotice, DeliveredNotice, PickupNotice, ReturnNotice, DispatchNotice]
}

// @fixed ListMirrorsFolder
export function registeredParcelNotices(): (typeof ParcelNotice)[] {
    return notices
}

class ParcelStage {
    static readonly Waiting = 'waiting'
}

function routeParcel(word: string, send: (word: string) => void): void {
    send(word)
}

// @fixed UnnamedVocabularyLiteral
export function sendByName(send: (word: string) => void): void {
    routeParcel(ParcelStage.Waiting, send)
}

export function sendByLiteral(send: (word: string) => void): void {
    // @sin UnnamedVocabularyLiteral
    routeParcel('waiting', send)
}

// @righteous UnnamedVocabularyLiteral
export function sendOther(send: (word: string) => void): void {
    routeParcel('custom', send)
}
