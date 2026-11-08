import type { Price } from './price';

export function total(prices: Price[]): number {
    return prices.reduce((sum, price) => sum + price.cents, 0);
}
