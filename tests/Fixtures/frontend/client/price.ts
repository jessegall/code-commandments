export class Price {
    private constructor(readonly cents: number, readonly currency: string) {}

    static of(cents: number, currency: string): Price {
        return new Price(Math.max(0, cents), currency.toUpperCase())
    }
}
