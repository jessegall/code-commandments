// @contract fact/function/async = true
// @contract fact/function/generator = true
// @contract fact/function/form = "function"
export async function* pages(source: AsyncIterable<number[]>): AsyncGenerator<number> {
    // @contract fact/loop/async = true
    for await (const page of source) {
        // @contract kind/yield
        // @contract fact/yield/delegates = true
        // @contract role/yield/value -> pagevalue
        // @label pagevalue reference
        yield* page
    }
}
