export class PrintSettings {
    // @fixed MemberOutOfOrder
    static readonly CopiesLimit = 8

    // @fixed MemberAfterMethod
    private copies = 1

    printCount(): number {
        return this.copies
    }

    // @righteous MemberAfterMethod
    get spareCopies(): number {
        return PrintSettings.CopiesLimit - this.copies
    }
}

// @righteous MemberOutOfOrder
export class InheritedPrintSettings extends PrintSettings {
    static readonly MinCopies = 2
    duplex = false
}

// @righteous PositionalTupleReturn
export function printSpan(first: number): [number, boolean] {
    return [first, first > 0]
}

// @righteous AssembledTemplate
export function printCategory(name: string): string {
    return ['Receipt', name, 'Customer'].join(': ')
}

// @fixed AssembledTemplate
export function printCard(name: string): string {
    return `Receipt:
${name}
Customer copy.`
}
