interface Item {
    // @contract fact/field/optional = true
    name?: string
    tags: string[]
}

export class Registry {
    // @label addm function
    add(item: Item): void {}
}

declare function identity<T>(value: T): T
declare function maybe(): (() => Item) | undefined

// @contract role/function/parameters -> [pitems, pregistry, pfactory, pextra, pmore]
// @contract role/function/returnType -> fret
// @contract role/function/body -> fbody
export function expressions(
    // @label pitems parameter
    items: Item[] | null,
    // @label pregistry parameter
    registry: Registry,
    // @label pfactory parameter
    factory: () => new () => Item,
    // @contract fact/parameter/optional = true
    // @label pextra parameter
    extra?: number,
    // @contract fact/parameter/collects = "rest"
    // @label pmore parameter
    ...more: string[]
// @label fret type-expression
// @label fbody block
): string {
    // @contract kind/variable
    // @contract fact/variable/form = "let"
    // @contract role/variable/declarators -> totdecl
    // @contract role/variable/declaredType -> totype
    // @label totdecl declarator
    // @label totype type-expression
    let total: number = 0
    // @contract fact/variable/form = "const"
    // @contract kind/index
    // @contract fact/index/nullConditional = true
    // @contract role/index/subject -> itemsref
    // @contract role/index/key -> indexzero
    // @label itemsref reference
    // @label indexzero literal
    const first = items?.[0]
    // @contract kind/construction
    // @contract role/construction/callee -> factorycall
    // @label factorycall call
    const made = new (factory())()
    // @contract role/construction/type -> maptype
    // @contract fact/type-expression/form at 24 = "named"
    // @contract role/type-expression/typeArguments at 24 -> [mapkey, mapvalue]
    // @label maptype type-expression 24
    // @label mapkey type-expression 28
    // @label mapvalue type-expression 36
    const lookup = new Map<string, number>()
    // @contract kind/call
    // @contract role/call/callee -> idref
    // @contract role/call/typeArguments -> idtype
    // @contract role/call/arguments -> idarg
    // @contract kind/argument
    // @contract role/argument/value -> idlit
    // @contract kind/literal
    // @contract fact/literal/literalKind = "string"
    // @contract fact/*/name on reference = "identity"
    // @label idref reference
    // @label idtype type-expression
    // @label idarg argument
    // @label idlit literal
    const same = identity<string>('x')
    // @contract fact/*/target on call -> addm
    registry.add(made)
    maybe()?.()
    // @contract kind/cast
    // @contract fact/cast/form = "assertion"
    // @contract role/cast/operand -> castop
    // @contract role/cast/targetType -> casttype
    // @label castop reference
    // @label casttype type-expression
    const forced = first as Item
    // @contract kind/type-test
    // @contract fact/type-test/form = "instanceof"
    // @contract role/type-test/subject -> ttsubject
    // @contract role/type-test/testedType -> tttype
    // @label ttsubject reference
    // @label tttype type-expression
    const kind = forced instanceof Registry ? 'registry' : 'item'
    // @contract kind/interpolation
    // @contract role/interpolation/parts -> [itext, iref]
    // @contract kind/text
    // @contract fact/text/value = "item "
    // @label itext text
    // @label iref reference
    const label = `item ${kind}`
    // @contract kind/pattern at 11
    // @contract fact/pattern/form at 11 = "sequence"
    // @contract role/pattern/subpatterns at 11 -> [headbind, prest]
    // @contract role/pattern/bindings at 18 -> [tailbind]
    // @label tailbind binding 21
    // @contract role/fallback/subject -> fbsubject
    // @contract role/fallback/replacement -> fbreplace
    // @contract fact/collection/form at 38 = "list"
    // @label prest pattern 18
    // @label headbind binding 12
    // @label fbsubject reference
    // @label fbreplace collection
    const [head, ...tail] = items ?? []
    const { name = 'unnamed', ...others } = forced
    const negated = !label
    // @contract fact/collection/form at 18 = "object"
    // @contract role/collection/entries at 18 -> [spreadentry, tagsentry]
    // @contract kind/entry at 20
    // @contract fact/entry/expansion at 20 = "keyed"
    // @contract fact/entry/expansion at 38 = "positional"
    // @label spreadentry entry 20
    // @label tagsentry entry 31
    const copy = { ...others, tags: [...tail] }
    // @contract fact/assignment/mode = "compound"
    // @contract fact/assignment/operator = "add"
    // @contract role/assignment/targets -> totalwrite
    // @contract role/assignment/values -> sumvalue
    // @contract fact/binary/operator at 14 = "add"
    // @label totalwrite reference 5
    // @label sumvalue binary 14
    total += (extra ?? 0) + more.length

    return label + same + head + negated + copy + lookup.size + name + total
}
