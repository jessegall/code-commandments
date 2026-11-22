<script lang="ts">
import * as helpers from './helpers'
import type { Item } from './item'

declare const Token: symbol
declare function sealed(target: unknown): void
declare function tracked(target: object, key: string): void
declare function memo(target: object, key: string, descriptor: PropertyDescriptor): void
declare function inject(token: symbol): (target: object, key: string | undefined, index: number) => void

export type Pair<T = string> = [first: T, second: T]

export type Handler<E extends object = {}> = (event: E, ...rest: unknown[]) => void

export interface Named<T extends object = {}> {
    name: T
}

export interface Shape {
    area(): number
}

export enum Color {
    Red = 1,
    Green,
}

export enum Level {
    Low = 1,
    High = Low + 1,
}

class Base<T> {
    protected value?: T
}

@sealed
export class Box<T extends Named = Named> extends Base<T> implements Named<T>, Shape {
    @tracked
    count: number = 0

    private readonly side: number = 2
    static readonly UNITS = 'cm'

    constructor(@inject(Token) private readonly service: Named) {
        super()
    }

    area(): number {
        return this.side * this.side
    }

    @memo
    method<U>(value: U = this.fallback()): U {
        return value
    }

    private fallback(): never {
        throw new Error('none')
    }
}

export const top = Level.High
export const ratio: number = 1 / 2
export const run = (item: Item): string => helpers.name(item)

export async function* pages(source: AsyncIterable<number[]>): AsyncGenerator<number> {
    for await (const page of source) {
        yield* page
    }
}
</script>
