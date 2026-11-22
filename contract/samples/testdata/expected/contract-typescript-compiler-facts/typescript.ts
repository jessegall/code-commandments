export interface Shape {
    area(): number
}

export class Square implements Shape {
    private readonly side: number = 2
    static readonly UNITS = 'cm'

    area(): number {
        return this.side * this.side
    }
}

export enum Level {
    Low = 1,
    High = Low + 1,
}

export const top = Level.High
export const ratio: number = 1 / 2
