import { ShelfCode } from './shelf-code'

// @righteous RepeatedStaticFactory
export function either(written: string): string {
    if (written.endsWith('0')) {
        return String(ShelfCode.parse(written).slot)
    }

    return ShelfCode.parse(written).aisle
}

// @righteous RepeatedStaticFactory
export function moved(written: string, target: string): boolean {
    let code = written
    const before = ShelfCode.parse(code).aisle
    code = target

    return before !== ShelfCode.parse(code).aisle
}

// @righteous RepeatedStaticFactory
export function apart(from: string, to: string): boolean {
    return ShelfCode.parse(from).aisle === ShelfCode.parse(to).aisle
}

// @righteous RepeatedStaticFactory
export function described(written: string, read: () => string): string {
    try {
        return read()
    } catch (error) {
        return `${ShelfCode.parse(written).aisle}: ${String(error)}`
    } finally {
        console.debug(written)
    }
}

// @righteous RepeatedStaticFactory
export function paired(first: string, second: string): string[] {
    const aisles: string[] = []
    {
        const written = first
        aisles.push(ShelfCode.parse(written).aisle)
    }
    if (second !== first) {
        const written = second
        aisles.push(ShelfCode.parse(written).aisle)
    }

    return aisles
}
