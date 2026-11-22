export function firstMatch(rows: number[][], wanted: number): number {
    let found = -1

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
    }

    while (found < 0) ;

    return found
}
