<script lang="ts">
export class Counter implements Iterator<number> {
    private current = 0

    // @contract fact/*/obligations on function = [{"symbol":"Iterator.next","origin":"external"}]
    next(): IteratorResult<number> {
        this.current += 1
        return { done: false, value: this.current }
    }
}
</script>
