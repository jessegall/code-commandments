abstract class CouponLimit {
    abstract cap(value: number): number
}

class NoCouponLimit extends CouponLimit {
    cap(value: number): number { return value }
}
class NewCustomerLimit extends CouponLimit {
    cap(value: number): number { return Math.min(500, value) }
}
class WeekendLimit extends CouponLimit {
    cap(value: number): number { return Math.floor(value / 2) }
}
class BulkLimit extends CouponLimit {
    cap(value: number): number { return Math.max(0, value - 300) }
}
class PrintedLimit extends CouponLimit {
    cap(value: number): number { return Math.round(value / 100) * 100 }
}

export function couponLimits(includeOnlineLimits: boolean): (typeof CouponLimit)[] {
    if (!includeOnlineLimits) {
        return [PrintedLimit]
    }

    // @sin ListMirrorsFolder
    return [NoCouponLimit, NewCustomerLimit, WeekendLimit, BulkLimit, PrintedLimit]
}

class CouponChannel {
    static readonly Counter = 'counter'
}

function applyChannel(word: string, send: (word: string) => void): void {
    send(word)
}

// @fixed UnnamedVocabularyLiteral
export function sendByName(send: (word: string) => void): void {
    applyChannel(CouponChannel.Counter, send)
}

export function sendByLiteral(send: (word: string) => void): void {
    // @sin UnnamedVocabularyLiteral
    applyChannel('counter', send)
}

// @righteous UnnamedVocabularyLiteral
export function sendOther(send: (word: string) => void): void {
    applyChannel('custom', send)
}
