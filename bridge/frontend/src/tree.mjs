/** How much source a file may hold for the checker to type its expressions: a larger one, generated code in practice, is read by its syntax alone. */
const CHECKED_SOURCE = 1 << 20

/** One node of the version 5 tree before it is numbered: its kind, its UTF-16 range in the file, facts, owned parts and links. */
export class Node {
    constructor(kind, start, end) {
        this.kind = kind
        this.start = start
        this.end = end
        this.id = -1
        this.facts = {}
        this.parts = {}
        this.links = {}
    }

    /** Sets a fact, left out when it says nothing: undefined, null, false, an empty text or an empty list. */
    fact(name, value) {
        if (value !== undefined && value !== null && value !== false && value !== '' && !(Array.isArray(value) && value.length === 0)) this.facts[name] = value

        return this
    }

    /** Sets a fact whatever its value, for one whose false or empty value is still said: a literal's. */
    said(name, value) {
        this.facts[name] = value

        return this
    }

    holds(role, node) {
        if (node) this.parts[role] = node

        return this
    }

    holdsAll(role, nodes) {
        const held = nodes.filter(Boolean)
        if (held.length) this.parts[role] = held

        return this
    }

    link(name, target) {
        this.links[name] = target

        return this
    }

    /** Every node it owns, in source order. */
    children() {
        return Object.values(this.parts).flat().sort((a, b) => a.start - b.start || b.end - a.end)
    }

    /** Numbers the tree in pre-order, children in source order, and lists its nodes by number. */
    numbered() {
        const order = []
        const pending = [this]
        while (pending.length) {
            const node = pending.pop()
            node.id = order.length
            order.push(node)
            pending.push(...node.children().reverse())
        }

        return order
    }

    /** The node as the stream writes it, its span in the file's bytes. */
    written(source) {
        const out = { id: this.id, kind: this.kind, span: source.span(this.start, this.end), ...this.facts }
        const roles = Object.entries(this.parts)
        if (roles.length) out.parts = Object.fromEntries(roles.map(([role, held]) => [role, Array.isArray(held) ? held.map((child) => child.written(source)) : held.written(source)]))
        const links = Object.entries(this.links)
        if (links.length) out.links = Object.fromEntries(links.map(([name, target]) => [name, Array.isArray(target) ? target.map((each) => each.id) : target.id]))

        return out
    }
}

/** One file's reading: the resolver's counts and the distinct types its nodes carry. */
export class Tree {
    constructor(source) {
        this.source = source
        this.checked = source.text.length <= CHECKED_SOURCE
        this.expressions = 0
        this.typed = 0
        this.calls = 0
        this.resolved = 0
        this.names = 0
        this.types = []
        this.typeIndexes = new Map()
    }

    /** The index in the file's `types` of the checker's `type`, described by `describe` the first time the file meets it. */
    typeIndex(type, describe) {
        if (!this.typeIndexes.has(type)) {
            this.typeIndexes.set(type, this.types.length)
            this.types.push(describe())
        }

        return this.typeIndexes.get(type)
    }

    /**
     * The contract's comments from `found` (`{start, end, form}` in UTF-16 offsets) over the numbered `nodes`: a leading
     * comment belongs to the outermost node starting at the first token after it, unless a blank line comes between, a
     * trailing one to the outermost node ending on its line before it. `fragment` reads a line or block comment's
     * words as code.
     */
    comments(found, nodes, fragment) {
        const source = this.source
        const owners = nodes.filter((node) => !['file', 'module', 'block'].includes(node.kind))
        const sorted = [...new Map(found.map((comment) => [comment.start, comment])).values()].sort((a, b) => a.start - b.start)
        const after = new Map(sorted.map((comment) => [comment.start, comment.end]))

        return sorted.map((comment, id) => {
            const out = { id, form: comment.form, text: source.text.slice(comment.start, comment.end), span: source.span(comment.start, comment.end) }
            if (!source.leadsItsLine(comment.start)) {
                const lineStart = lineStartOf(source.text, comment.start)
                const owner = owners.find((node) => node.end <= comment.start && node.end > lineStart)
                if (owner) out.attached = owner.id
                out.trailing = true
            } else {
                const next = leads(source.text, comment.end, after)
                const owner = next === undefined ? undefined : owners.find((node) => node.start === next && node.end > node.start)
                if (owner) out.attached = owner.id
            }
            if (comment.form === 'line' || comment.form === 'block') out.fragment = fragment(out.text)

            return out
        })
    }
}

/** Where the code a comment leads starts, other comments skipped; none past a blank line. */
function leads(text, at, after) {
    for (;;) {
        let breaks = 0
        while (at < text.length && /\s/.test(text[at])) breaks += text[at++] === '\n' ? 1 : 0
        if (breaks > 1) return undefined
        if (!after.has(at)) return at
        at = after.get(at)
    }
}


function lineStartOf(text, index) {
    return text.lastIndexOf('\n', index - 1) + 1
}
