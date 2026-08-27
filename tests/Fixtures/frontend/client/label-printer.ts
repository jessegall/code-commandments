// A third shape for both rules, in a class that does printing rather than holding a cart or an
// order — so the scenarios are genuinely different code, not the same class renamed.

interface Printer {
    id: string
}

export class LabelPrinter {
    // @sin FalselyOptionalField
    private queue?: string[] = []

    private readonly printer: Printer = { id: 'default' }

    // @fixed FalselyOptionalField
    // @righteous FalselyOptionalField
    // @fixed DefendedCertainField
    private lastError?: string

    target(): string {
        // @sin DefendedCertainField
        return this.printer?.id
    }

    lastFailure(): string {
        // @fixed DefendedCertainField
        // @righteous DefendedCertainField
        return this.lastError?.trim() ?? ''
    }
}
