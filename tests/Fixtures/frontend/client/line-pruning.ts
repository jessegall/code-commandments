// Dropping the empty lines of a page. The walk reads the array as it stood when it began, so splicing out of it
// mid-walk moves every later line up one and the walk steps over the next of them. Below it, the FIX: decide while
// walking, change once afterwards.
export function prune(lines: string[]): void {
    for (const line of lines) {
        if (line.trim() === '') {
            // @sin CollectionMutatedWhileIterated
            lines.splice(lines.indexOf(line), 1)
        }
    }
}

// @fixed CollectionMutatedWhileIterated
export function pruned(lines: string[]): string[] {
    const empty: string[] = []

    for (const line of lines) {
        if (line.trim() === '') {
            empty.push(line)
        }
    }

    return lines.filter((line) => !empty.includes(line))
}
