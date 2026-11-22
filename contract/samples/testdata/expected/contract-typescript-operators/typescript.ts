declare function gather(...parts: number[]): void
declare const holder: { reach?: number }
declare const list: number[]
declare const ready: boolean
declare const queued: boolean
// @contract fact/type-expression/form at 22 = "array"
// @contract role/type-expression/element at 22 -> queuefn
// @label queuefn type-expression 23
declare const queue: (() => void)[]
// @contract fact/type-expression/form at 21 = "union"
// @contract role/type-expression/members at 21 -> [modea, modeb]
// @label modea type-expression 23
// @label modeb type-expression 29
declare const mode: | 'a' | 'b'

export function operators(): void {
    // @contract fact/access/nullConditional = true
    const reach = holder?.reach
    // @contract fact/argument/spread = "positional"
    gather(...list)
    // @contract role/logical/left -> readyref
    // @contract role/logical/right -> queuedref
    // @label readyref reference 18
    // @label queuedref reference 27
    const flag = ready && queued
    // @contract kind/entry at 21
    // @contract role/entry/key at 21 -> idkey
    // @contract role/entry/value at 21 -> idvalue
    // @label idkey literal 21
    // @label idvalue literal 27
    const table = { 'id': 1, 'tags': list }
    // @contract kind/unsupported
    // @contract fact/unsupported/construct = "comma"
    // @contract role/unsupported/parts -> [firstcall, secondcall]
    // @label firstcall call 5
    // @label secondcall call 16
    gather(1), gather(2)
}
