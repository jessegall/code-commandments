export async function* pages(source: AsyncIterable<number[]>): AsyncGenerator<number> {
    for await (const page of source) {
        yield* page
    }
}
