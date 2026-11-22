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

export enum Color {
    Red = 1,
    Green,
}

class Base<T> {
    protected value?: T
}

@sealed
export class Box<T extends Named = Named> extends Base<T> implements Named<T> {
    @tracked
    count: number = 0

    constructor(@inject(Token) private readonly service: Named) {
        super()
    }

    @memo
    method<U>(value: U = this.fallback()): U {
        return value
    }

    private fallback(): never {
        throw new Error('none')
    }
}
