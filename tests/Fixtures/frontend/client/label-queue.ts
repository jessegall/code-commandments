// The queue of labels still to print. Taking the printed one off the front while walking moves every label left by
// one, so the walk's next step lands past the one that moved up: every other label is printed and the rest are
// never seen.
export function printAll(queue: string[], print: (label: string) => void): void {
    for (const label of queue) {
        print(label)
        // @sin CollectionMutatedWhileIterated
        queue.shift()
    }
}

export function waiting(queue: string[]): number {
    return queue.length
}
