# TypeScript absence — say what is missing, and mean it — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### defended-certain-field

An `?.` on a field the class declares as always present — a defence against a case the type says cannot happen, so the code doubts something the design already rules out.

```ts
----------[ Bad ]----------

customerName(): string {
    return this.customer?.name
}

----------[ Good ]----------

// The FIX: `customer` is always set, so it is read plainly.
displayName(): string {
    return this.customer.name
}
```

### falsely-optional-field

A field declared optional (`x?: T`, `T | null`) that is initialised where it is declared — it is never absent, and every `?.` and `??` downstream defends a case that cannot happen

```ts
----------[ Bad ]----------

export class CartSession {
    private items?: Item[] = []

    private currency?: string = 'EUR'

    private coupon?: Coupon

    private readonly openedAt: string = '1970-01-01'

    count(): number {
        return this.items.length
    }

    couponCode(): string {
        return this.coupon?.code ?? 'none'
    }
}

----------[ Good ]----------

// The FIX: a field initialised where it is declared is never absent, so it is declared without the `?`.
export class SavedCart {
    private items: Item[] = []

    size(): number {
        return this.items.length
    }
}
```
