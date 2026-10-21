// The righteous twins of a walk that changes what it walks. The first walks a COPY, which is a different array and
// says so, and may change the original as it goes; the second changes the original and leaves the walk where it
// stands, so the ground it moved is never read again.
// @righteous CollectionMutatedWhileIterated
export function packed(crates: string[]): void {
    for (const crate of [...crates]) {
        if (crate === '') {
            crates.splice(crates.indexOf(crate), 1)
        }
    }
}

// @righteous CollectionMutatedWhileIterated
export function dropFirstEmpty(crates: string[]): void {
    for (const crate of crates) {
        if (crate === '') {
            crates.splice(crates.indexOf(crate), 1)

            break
        }
    }
}

// A walk that GROWS is a work queue, which is how JavaScript writes one: each crate brings in what it is bound to,
// and the walk reaches those in turn. Nothing in the code tells that from the same lines written by mistake, so it
// is not the sin; neither is pruning a set while walking it, which the language defines.
// @righteous CollectionMutatedWhileIterated
export function closure(ids: Set<string>, boundTo: (id: string) => string[]): void {
    for (const id of ids) {
        for (const bound of boundTo(id)) {
            ids.add(bound)
        }
    }
}
