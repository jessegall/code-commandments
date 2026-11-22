import { ShelfCode } from './shelf-code'

export function stop(written: string): string {
    // @sin RepeatedStaticFactory
    return `${ShelfCode.parse(written).aisle}/${ShelfCode.parse(written).slot}`
}

// @fixed RepeatedStaticFactory
export function stopOnce(written: string): string {
    const code = ShelfCode.parse(written)

    return `${code.aisle}/${code.slot}`
}
