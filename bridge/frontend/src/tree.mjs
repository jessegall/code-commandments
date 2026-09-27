/** One file's nodes as they are numbered: pre-order ids, each node's byte span for attaching comments, and the resolver's counts. */
export class Tree {
    constructor(source) {
        this.source = source
        this.next = 0
        this.spans = []
        this.expressions = 0
        this.typed = 0
        this.calls = 0
        this.resolved = 0
        this.names = 0
        this.types = []
        this.typeIndexes = new Map()
        this.described = new Map()
    }

    /** The index in the file's `types` of the checker's `type`, described by `describe` the first time the file meets it. */
    typeIndex(type, describe) {
        if (!this.typeIndexes.has(type)) {
            this.typeIndexes.set(type, this.types.length)
            this.types.push(describe())
        }

        return this.typeIndexes.get(type)
    }

    /** The next id, for a node over the UTF-16 range `[start, end)`. */
    number(start, end) {
        const id = this.next++
        this.spans.push({ start: this.source.bytes[start], end: this.source.bytes[end], id })

        return id
    }

    /**
     * The contract's comments from `found` (`{start, end, kind}` in UTF-16 offsets): a leading comment belongs to the
     * outermost node starting at the first token after it, a trailing one to the outermost node ending on its line before it.
     */
    comments(found) {
        const source = this.source
        const sorted = [...new Map(found.map((comment) => [comment.start, comment])).values()].sort((a, b) => a.start - b.start)
        const after = new Map(sorted.map((comment) => [comment.start, comment.end]))

        return sorted.map((comment, id) => {
            const [start, end] = [source.bytes[comment.start], source.bytes[comment.end]]
            const out = { id, kind: comment.kind, text: source.text.slice(comment.start, comment.end), span: source.span(comment.start, comment.end) }
            if (!source.leadsItsLine(comment.start)) {
                const lineStart = source.bytes[lineStartOf(source.text, comment.start)]
                const owner = this.outermost((span) => span.end <= start && span.end > lineStart)
                if (owner) out.attached = owner.id
                out.trailing = true

                return out
            }
            let next = source.skipWhitespace(comment.end)
            while (after.has(next)) next = source.skipWhitespace(after.get(next))
            const owner = this.outermost((span) => span.start === source.bytes[next] && span.end > span.start)
            if (owner) out.attached = owner.id

            return out
        })
    }

    /** The first node in pre-order a check holds for: the outermost of those that nest. */
    outermost(check) {
        return this.spans.find(check)
    }
}

function lineStartOf(text, index) {
    return text.lastIndexOf('\n', index - 1) + 1
}
