interface Source {
    name: string
    read(): Promise<string>
    close(): void
}

export async function load(source: Source): Promise<string> {
    try {
        return await source.read()
    } catch (error) {
        throw new Error(`cannot read ${source.name}: ${error}`)
    } finally {
        source.close()
    }
}
