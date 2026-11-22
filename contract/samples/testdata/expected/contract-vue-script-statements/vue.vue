<script lang="ts">
interface Source {
    name: string
    read(): Promise<string>
    close(): void
}

export function describe(code: number, flag: boolean): string {
    switch (code) {
        case 0:
        case 1:
            return 'low'
        case 2:
            flag = !flag
            break
        default:
            return 'high'
    }

    return flag ? 'flagged' : 'plain'
}

export function firstMatch(rows: number[][], wanted: number): number {
    let found = -1
    let total = 0

    search: for (const row of rows) {
        for (const cell of row) {
            if (cell === wanted) {
                found = cell
                break search
            } else if (cell < 0) {
                continue search
            }
        }
    }

    for (let low = 0, high = 10; low < high; ++low, --high) {
        found += low
        total = total + low * high
    }

    do {
        total -= 1
    } while (total > 0)

    while (found < 0) ;

    return found + total
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
</script>
