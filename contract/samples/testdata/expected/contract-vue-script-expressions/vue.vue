<script lang="ts">
interface Item {
    name?: string
    tags: string[]
}

export class Registry {
    // @contract fact/function/form = "method"
    add(item: Item): void {}
}

// @label identityFn function
// @contract role/function/typeParameters -> [identT]
// @label identT type-parameter
declare function identity<T>(value: T): T
declare function maybe(): (() => Item) | undefined
// @contract fact/parameter/collects = "rest"
declare function spread(...values: number[]): number

export function expressions(
    items: Item[] | null,
    registry: Registry,
    factory: () => new () => Item,
    extra?: number,
    ...more: string[]
): string {
    let total: number = 0
    // @contract kind/index
    // @contract fact/index/nullConditional = true
    // @contract role/index/subject -> itemsSubject
    // @label itemsSubject reference
    // @contract role/index/key -> zeroKey
    // @label zeroKey literal
    const first = items?.[0]
    // @contract kind/construction
    // @contract role/construction/callee -> factoryCall
    // @label factoryCall call
    const made = new (factory())()
    // @contract role/construction/type -> mapType
    // @label mapType type-expression 24
    // @contract fact/type-expression/form at 24 = "named"
    // @contract role/type-expression/typeArguments at 24 -> [mapString, mapNumber]
    // @label mapString type-expression 28
    // @label mapNumber type-expression 36
    const lookup = new Map<string, number>()
    // @contract role/call/typeArguments -> [sameString]
    // @label sameString type-expression
    // @contract role/call/arguments -> [xArg]
    // @label xArg argument
    // @contract fact/*/target on call -> identityFn
    const same = identity<string>('x')
    // @contract role/call/callee -> addAccess
    // @label addAccess access
    // @contract role/call/arguments -> [madeArg]
    // @label madeArg argument
    registry.add(made)
    maybe()?.()
    // @contract kind/cast
    // @contract fact/cast/form = "assertion"
    // @contract role/cast/operand -> firstOperand
    // @label firstOperand reference
    // @contract role/cast/targetType -> itemTarget
    // @label itemTarget type-expression
    const forced = first as Item
    // @contract kind/type-test
    // @contract fact/type-test/form = "instanceof"
    // @contract role/type-test/subject -> forcedSubject
    // @label forcedSubject reference 18
    // @contract role/type-test/testedType -> registryTested
    // @label registryTested type-expression
    const kind = forced instanceof Registry ? 'registry' : 'item'
    // @contract kind/interpolation
    // @contract role/interpolation/parts -> [itemText, kindPart]
    // @label itemText text
    // @contract kind/text
    // @contract fact/text/value = "item "
    // @label kindPart reference
    const label = `item ${kind}`
    // @contract kind/pattern at 11
    // @contract fact/pattern/form at 11 = "sequence"
    // @contract fact/pattern/form at 18 = "rest"
    // @contract role/pattern/bindings at 18 -> [tailBinding]
    // @label tailBinding binding 21
    // @contract kind/fallback
    // @contract fact/fallback/trigger = "null"
    // @contract role/fallback/subject -> itemsFallback
    // @label itemsFallback reference
    // @contract role/fallback/replacement -> emptyList
    // @label emptyList collection
    // @contract fact/collection/form = "list"
    const [head, ...tail] = items ?? []
    // @contract fact/pattern/form at 11 = "mapping"
    // @contract role/pattern/subpatterns at 11 -> [namePattern, othersRest]
    // @label namePattern pattern 13
    // @label othersRest pattern 31
    // @contract fact/pattern/form at 13 = "binding"
    // @contract role/pattern/bindings at 13 -> [nameBinding]
    // @label nameBinding binding 13
    // @contract role/pattern/value at 13 -> unnamedLit
    // @label unnamedLit literal
    // @contract fact/pattern/form at 31 = "rest"
    // @contract role/pattern/bindings at 31 -> [othersBinding]
    // @label othersBinding binding 34
    const { name = 'unnamed', ...others } = forced
    const negated = !label
    // @contract kind/collection at 18
    // @contract fact/collection/form at 18 = "object"
    // @contract role/collection/entries at 18 -> [spreadEntry, tagsEntry]
    // @label spreadEntry entry 20
    // @label tagsEntry entry 31
    // @contract kind/entry at 20
    // @contract fact/entry/expansion at 20 = "keyed"
    // @contract role/entry/key at 31 -> tagsKey
    // @label tagsKey reference 31
    // @contract role/entry/value at 31 -> tailList
    // @label tailList collection 37
    // @contract fact/collection/form at 37 = "list"
    // @contract fact/entry/expansion at 38 = "positional"
    const copy = { ...others, tags: [...tail] }
    // @contract kind/assignment
    // @contract fact/assignment/mode = "compound"
    // @contract fact/assignment/operator = "add"
    // @contract kind/argument
    // @contract fact/argument/spread = "positional"
    // @contract role/call/arguments -> [spreadArg]
    // @label spreadArg argument
    // @contract role/argument/value -> pairList
    // @label pairList collection
    total += (extra ?? 0) + more.length + spread(...[1, 2])

    return label + same + head + negated + copy + lookup.size + name + total
}
</script>
