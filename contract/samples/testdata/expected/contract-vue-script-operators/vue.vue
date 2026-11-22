<script lang="ts">
interface Box {
    label?: string
}

export function operators(box: Box | null, left: boolean, right: boolean): boolean {
    // @contract kind/access
    // @contract fact/access/nullConditional = true
    const text = box?.label
    // @contract kind/logical
    // @contract fact/logical/operator = "and"
    // @contract role/logical/left -> andLeft
    // @contract role/logical/right -> andRight
    // @label andLeft reference 18
    // @label andRight reference 26
    const both = left && right
    // @contract kind/logical
    // @contract fact/logical/operator = "or"
    const either = left || right
    // @contract kind/comparison
    // @contract fact/comparison/operator = "not-identical"
    // @contract fact/literal/literalKind = "undefined"
    const present = text !== undefined

    return present ? both : either
}
</script>
