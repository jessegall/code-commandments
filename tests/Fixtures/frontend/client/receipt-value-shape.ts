let receiptUnits = 0

export function receiptTick(written: readonly number[]): number {
    for (const units of written) {
        // @sin MutableStaticState
        receiptUnits += units
    }
    return receiptUnits
}

export function hasRefund(
    // @sin ArrayBag
    slips: Record<string, number>,
): boolean {
    if (slips['paid'] === 0) {
        return false
    }
    return slips['refunded'] > 0
}

function foldReceipt(
    // @sin ArrayBagParameter
    costs: Record<string, number>,
): number {
    return Object.values(costs).reduce((sum, cost) => sum + cost, 0)
}

export function compareReceiptQuotes(): number {
    return foldReceipt({ postage: 10, fees: 2 }) - foldReceipt({ postage: 20, fees: 4 })
}

export function receiptTotals(net: number) {
    const vat = net / 10
    // @sin ArrayReturnBag
    return { gross: net + vat, vat, net }
}

class ReceiptDestination {
    constructor(readonly district: string) {}
}

class ReceiptShapeFields {
    constructor(readonly copies: number, readonly counterId: number, readonly destination: ReceiptDestination) {}
}

// @sin FeatureEnvy
export function receiptDescription(fields: ReceiptShapeFields): string {
    if (fields.copies <= 0) {
        return 'No copies for counter ' + fields.counterId
    }

    return fields.copies + ' copies for ' + fields.destination.district + ' at ' + fields.counterId
}

// @sin KeyedLookupEnvy
export function receiptCounter(counters: Record<number, string>, fields: ReceiptShapeFields): string {
    return counters[fields.counterId]
}

export class ReceiptShapePrinter {
    // @sin DataClump
    print(copies: number, counterId: number, store: string): string[] {
        return Array.from({ length: copies }, (_, at) => store + '/' + counterId + '/' + at)
    }
}

export class ReceiptShapeValidator {
    // @sin DataClump
    canSend(counterId: number, copies: number, store: string): boolean {
        if (store.length === 0 || copies < 1) {
            return false
        }
        return counterId >= 10
    }
}

export class ReceiptShapeState {
    private counter = 0
    private title = ''

    // @sin CoupledFields
    accept(counter: number, title: string): boolean {
        this.counter = counter
        this.title = title
        return this.counter > 0 && this.title.length >= 3
    }

    // @sin BareStatePredicate
    opens(): boolean {
        return this.counter >= 1
    }

    // @sin NarratedCommand
    prints(): void {
        this.counter += 1
        this.title = 'Copy ' + this.counter
    }

    // @sin ScratchStateRestore
    assessTitle(prefix: string): string {
        const saved = this.title
        this.title = prefix + this.title
        const result = this.title.toUpperCase()
        this.title = saved
        return result
    }
}

export class ReceiptShapeValue {
    constructor(private total: number, private currency: string, private returned: boolean) {}

    equals(other: ReceiptShapeValue): boolean {
        return this.total === other.total && this.currency === other.currency
    }

    // @sin MutableValueObject
    addDeposit(paid: number): void {
        if (paid > 0) {
            this.total += paid
        }
    }

    // @sin HandRolledWither
    withRefund(returned: boolean): ReceiptShapeValue {
        return new ReceiptShapeValue(this.total, this.currency, returned)
    }
}

export class ReceiptShapeShelf {
    private readonly receipts = new Map<number, ReceiptShapeValue>()

    get counterReceipts(): Map<number, ReceiptShapeValue> {
        // @sin HandedOutCollection
        return this.receipts
    }

    // @fixed HandedOutCollection
    snapshot(): Map<number, ReceiptShapeValue> {
        return new Map(this.receipts)
    }
}

export class ReceiptShapeStarter {
    constructor(printer: ReceiptShapePrinter) {
        // @sin ConstructorSideEffect
        printer.print(2, 25, 'Dock Street')
    }
}
