// The FIX for the packing slip's copied loop: the line layout lives in ONE exported function,
// and every document that lists order lines calls it instead of carrying its own copy.

interface Line {
    name: string
    quantity: number
    unitPrice: number
}

// @fixed DuplicateFunction
export function describeLines(lines: Line[]): string[] {
    return lines
        .filter((line) => line.quantity > 0)
        .map((line) => `${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
}

export class DeliveryNote {
    // @fixed DuplicateFunction
    rows(lines: Line[]): string[] {
        return describeLines(lines)
    }
}

export class ReturnForm {
    // @fixed DuplicateFunction
    lineItems(lines: Line[]): string[] {
        return describeLines(lines)
    }
}
