// The cart the checkout screen drives. Its optional fields are the subject: two of them are
// initialised where they are declared, so the `?` claims an absence the object never has.

interface Item {
    sku: string
    qty: number
}

interface Coupon {
    code: string
}

export class CartSession {
    // @sin FalselyOptionalField
    private items?: Item[] = []

    // @sin FalselyOptionalField
    private currency?: string = 'EUR'

    // @righteous FalselyOptionalField
    private coupon?: Coupon

    // @righteous FalselyOptionalField
    private readonly openedAt: string = '1970-01-01'

    count(): number {
        return this.items.length
    }

    couponCode(): string {
        return this.coupon?.code ?? 'none'
    }
}

// The FIX: a field initialised where it is declared is never absent, so it is declared without the `?`.
export class SavedCart {
    // @fixed FalselyOptionalField
    private items: Item[] = []

    size(): number {
        return this.items.length
    }
}
