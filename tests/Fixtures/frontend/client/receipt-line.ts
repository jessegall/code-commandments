import { Price } from './price'

export function describeLine(cents: number, currency: string, quantity: number): string {
    const unit = Price.of(cents, currency).cents
    const total = unit * quantity

    // @sin RepeatedStaticFactory
    return `${quantity} x ${unit} ${Price.of(cents, currency).currency} = ${total}`
}

// @fixed RepeatedStaticFactory
export function describeLineOnce(cents: number, currency: string, quantity: number): string {
    const price = Price.of(cents, currency)

    return `${quantity} x ${price.cents} ${price.currency} = ${price.cents * quantity}`
}
