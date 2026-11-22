<script lang="ts">
// @contract kind/alias
// @contract role/alias/underlyingType -> modeUnion
export type Mode =
    // @label modeUnion type-expression 5
    // @contract fact/type-expression/form at 5 = "union"
    // @contract role/type-expression/members at 5 -> [onType, offType]
    // @label onType type-expression 7
    // @contract fact/type-expression/form at 7 = "literal"
    | 'on'
    // @label offType type-expression 7
    | 'off'

// @contract role/variable/declaredType -> handlerList
// @label handlerList type-expression 25
// @contract fact/type-expression/form at 25 = "array"
// @contract role/type-expression/element at 25 -> handlerFn
// @label handlerFn type-expression 26
// @contract fact/type-expression/form at 26 = "function"
declare const handlers: (() => void)[]
</script>
