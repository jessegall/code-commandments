<script lang="ts">
export function sequence(a: number, b: number): number {
    // @contract kind/unsupported
    // @contract fact/unsupported/construct = "comma"
    // @contract role/unsupported/parts -> [bump, drop]
    // @label bump assignment 13
    // @label drop assignment 18
    return (a++, b++)
}
</script>
