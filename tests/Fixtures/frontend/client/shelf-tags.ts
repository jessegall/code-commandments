// The tags a shelf is marked with. Sorting them half way through the walk moves every tag the walk has not reached
// yet, so from there on it reads tags it has already read and misses others entirely.
export function tidy(tags: string[]): void {
    for (const tag of tags) {
        if (tag.startsWith('_')) {
            // @sin CollectionMutatedWhileIterated
            tags.sort()
        }
    }
}

export function marked(tags: string[]): string[] {
    return [...tags].sort()
}
