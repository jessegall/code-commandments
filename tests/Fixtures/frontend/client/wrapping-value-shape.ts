const reading = new Intl.NumberFormat('en')
let registeredRolls: string[] = []

export function wrappingTick(roll: string): number {
    if (roll.length > 0) {
        // @sin MutableStaticState
        registeredRolls = [...registeredRolls, roll]
    }
    return registeredRolls.length
}

export function wrappingLine(
    // @sin ArrayBag
    fields: Record<string, string>,
): string {
    const folds = Number(fields['folds'])
    return `${reading.format(folds)} folds of ${fields['paper']}`
}

function wrappingKeys(
    // @sin ArrayBagParameter
    fields: Record<string, string>,
): string[] {
    const parts: string[] = []
    for (const [setting, selected] of Object.entries(fields)) {
        if (selected.trim().length > 0) {
            parts.push(setting.toUpperCase() + '=' + selected)
        }
    }
    return parts
}

export function wrappingNameKeys(): string[] {
    const first = wrappingKeys({ paper: 'kraft', ribbon: 'moss' })
    const second = wrappingKeys({ paper: 'tissue', ribbon: 'clay' })
    return first.concat(second)
}

export function wrappingDefault(stock: string) {
    const paper = stock.length > 0 ? stock : 'recycled kraft'
    // @sin ArrayReturnBag
    return { paper, ribbon: paper.endsWith('kraft') ? 'cotton' : 'linen', stamp: 'fragile' }
}

class WrappingShapeFields {
    constructor(
        readonly sku: string,
        readonly rollCount: number,
        readonly contents: { liner: string; instructions: string[] },
    ) {}
}

// @sin FeatureEnvy
export function wrappingDescription(fields: WrappingShapeFields): string {
    const summary = `${fields.sku}: ${reading.format(fields.rollCount)} rolls lined with ${fields.contents.liner}`
    return [summary, ...fields.contents.instructions].join(', ')
}

// @sin KeyedLookupEnvy
export function wrappingCarrier(address: WrappingShapeFields, depot: Readonly<Record<string, number>>): number {
    return depot[address.sku]
}

export class WrappingShapePrinter {
    // @sin DataClump
    print(paper: string, ribbon: string, stamp: string): string {
        return [paper, ribbon].filter(Boolean).join(' + ') + ' / ' + stamp
    }
}

export class WrappingShapeValidator {
    // @sin DataClump
    check(paper: string, ribbon: string, stamp: string): boolean {
        return paper.startsWith('kraft') || ribbon !== '' && stamp.toLowerCase() === 'fragile'
    }
}

export class WrappingShapeState {
    private rolls = 0
    private reserved = 0
    private sealed = false

    // @sin CoupledFields
    approve(rolls: number, reserved: number): number {
        this.rolls = rolls
        this.reserved = reserved
        return this.rolls > 0 && this.reserved <= this.rolls ? this.rolls - this.reserved : 0
    }

    // @sin BareStatePredicate
    closes(): boolean {
        return this.sealed && this.rolls === this.reserved
    }

    // @sin NarratedCommand
    dispatches(): void {
        this.sealed = true
        this.reserved = this.rolls
    }

    // @sin ScratchStateRestore
    sample(temporary: number): string {
        const original = this.rolls
        this.rolls = temporary
        const display = reading.format(this.rolls)
        this.rolls = original
        return display
    }
}

export class WrappingShapeValue {
    constructor(private pattern: string, private width: number, private finish: string) {}

    equals(other: WrappingShapeValue): boolean {
        return this.pattern === other.pattern && this.width === other.width
    }

    // @sin MutableValueObject
    resize(width: number): void {
        this.width = width
    }

    // @sin HandRolledWither
    withPattern(pattern: string): WrappingShapeValue {
        return new WrappingShapeValue(pattern, this.width, this.finish)
    }
}

export class WrappingShapeShelf {
    private tags: string[] = []

    allTags(): string[] {
        // @sin HandedOutCollection
        return this.tags
    }

    // @fixed HandedOutCollection
    snapshot(): string[] {
        return this.tags.slice()
    }
}

export class WrappingShapeStarter {
    constructor(printer: WrappingShapePrinter) {
        // @sin ConstructorSideEffect
        printer.print('Mill Road', 'Central', '10001')
    }
}
