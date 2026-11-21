// The acceptance cases of the shared flow rules, each written once.

export function settle(cents: number): number {
    if (cents < 0) {
        return 0
        // @sin UnreachableStatement
        return -1
        // @righteous UnreachableStatement
        cents = 1
    }

    return cents
}

export function rounded(cents: number): number {
    {
        // @righteous ReassignedParameter
        let cents = 5
        cents = cents * 2
    }

    return cents
}

interface Cursor {
    advance(steps: number): Cursor
    done(): boolean
}

export function walk(start: Cursor): number {
    let visited = 0
    // @righteous NonCountingFor
    for (let cursor = start; !cursor.done(); cursor = cursor.advance(2)) {
        visited++
    }

    return visited
}
