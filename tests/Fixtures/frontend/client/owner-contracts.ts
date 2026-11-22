// @righteous LoneImplementerInterface
interface ReceiptFormatter {
    format(reference: string): string
}

class PlainReceipt implements ReceiptFormatter {
    format(reference: string): string {
        return reference.trim()
    }
}

class NumberedReceipt implements ReceiptFormatter {
    format(reference: string): string {
        return 'Receipt #' + reference
    }
}

export class ReceiptJob {
    constructor(
        // @righteous ConcreteCrossModuleDependency
        // @fixed ConcreteCrossModuleDependency
        // @righteous UnreadCollaborator
        // @fixed UnreadCollaborator
        private readonly formatter: ReceiptFormatter,
        private readonly reference: string,
    ) {}

    // @righteous MiddleMan
    // @fixed MiddleMan
    caption(): string {
        return this.formatCurrent() + ': closed'
    }

    // @righteous UnusedPrivateMember
    private formatCurrent(): string {
        return this.formatter.format(this.reference)
    }

    // @righteous UselessPropertyHook
    // @fixed UselessPropertyHook
    get key(): string {
        return this.reference
    }
}

export class LongReceipt extends PlainReceipt {
    // @righteous RefusedBequest
    // @fixed RefusedBequest
    override format(reference: string): string {
        return 'Customer copy: ' + super.format(reference)
    }
}

// @righteous HandRolledRegistry
export class ReceiptHistory {
    private readonly tags: Record<string, string> = {}

    remember(key: string, text: string): void {
        this.tags[key] = text
    }

    tag(key: string): string | undefined {
        // @sin NullableRegistryLookup
        return this.tags[key]
    }
}

// @righteous TrainWreck
// @fixed TrainWreck
export function receiptCaption(receipt: ReceiptJob): string {
    return receipt.caption()
}

enum DispatchStage {
    Waiting,
    Sent,
}

export class DispatchReceipt {
    private stage = DispatchStage.Waiting

    // @righteous GuardBesideTransition
    // @fixed GuardBesideTransition
    dispatch(): void {
        if (this.stage !== DispatchStage.Waiting) {
            // @sin GenericException
            // @sin MessageAtThrow
            throw new Error('Message already sent')
        }
        this.stage = DispatchStage.Sent
    }
}

interface DiscountShape {
    cents: number
}

// @righteous RedundantArrowReturnType
export const stableDiscount = (cents: number): DiscountShape => ({ cents })

// @fixed RedundantArrowReturnType
export const netDiscount = (price: number) => price - 1

export function receiptStage(state: DispatchStage): string {
    switch (state) {
        case DispatchStage.Waiting:
            return 'waiting'
        case DispatchStage.Sent:
            return 'sent'
        // @righteous MatchDefaultReturnsNull
        // @fixed MatchDefaultReturnsNull
        default:
            // @sin GenericException
            // @sin MessageAtThrow
            throw new Error('Unexpected dispatch stage')
    }
}
