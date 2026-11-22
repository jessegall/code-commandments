interface Source {
    name: string
    read(): Promise<string>
    close(): void
}

export async function load(source: Source): Promise<string> {
    // @contract kind/try
    // @contract role/try/body -> trybody
    // @contract role/try/handlers -> catchone
    // @contract role/try/finally -> finblock
    // @label trybody block
    try {
        // @contract kind/await
        // @contract role/await/operand -> readcall
        // @label readcall call
        return await source.read()
    // @contract fact/handler/catchForm = "catch-all"
    // @contract role/handler/binding -> errbind
    // @contract role/handler/body -> catchbody
    // @label catchone handler
    // @label errbind binding
    // @label catchbody block
    } catch (error) {
        throw new Error(`cannot read ${source.name}: ${error}`)
    // @contract kind/block
    // @contract role/block/statements -> closestmt
    // @label finblock block
    } finally {
        // @contract kind/expression-statement
        // @contract role/expression-statement/expression -> closecall
        // @label closestmt expression-statement
        // @label closecall call
        source.close()
    }
}
