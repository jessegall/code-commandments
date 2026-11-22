<script lang="ts">
export function skip(values: number[]): number {
    let total = 0
    // @label eachLoop loop
    for (const value of values) {
        if (value < 0) {
            // @contract kind/continue
            // @contract link/continue/destination -> eachLoop
            continue
        }
        total += value
    }
    return total
}
</script>
