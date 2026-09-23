// A packing slip and an invoice sheet each lay out order lines, and each class wrote its own
// copy of the same loop — so a change to how a line reads lands in one and not the other.

interface Line {
    name: string
    quantity: number
    unitPrice: number
}

export class PackingSlip {
    // @sin DuplicateFunction
    rows(lines: Line[]): string[] {
        const rows: string[] = []
        for (const line of lines) {
            if (line.quantity <= 0) {
                continue
            }
            rows.push(`${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
        }
        return rows
    }
}

export class InvoiceSheet {
    // @sin DuplicateFunction
    lineItems(lines: Line[]): string[] {
        const rows: string[] = []
        for (const line of lines) {
            if (line.quantity <= 0) {
                continue
            }
            rows.push(`${line.quantity} × ${line.name}: ${(line.quantity * line.unitPrice).toFixed(2)}`)
        }
        return rows
    }
}
