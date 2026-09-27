/** How much of a line is held before it is handed on. */
const CHUNK = 1 << 20

/**
 * The pieces of `value` as one JSON line, made as they are taken, byte for byte as `JSON.stringify(value) + '\n'`
 * writes it: a generated file's tree is a line of a hundred megabytes, too large to hold as one string beside itself.
 */
export function* lineOf(value) {
    let held = ''
    for (const text of tokens(value, false)) {
        held += text
        if (held.length >= CHUNK) {
            yield held
            held = ''
        }
    }
    yield held + '\n'
}

/** The JSON text of `item`, a token at a time; `inArray` says whether an unwritable value stands as `null`. */
function* tokens(item, inArray) {
    if (item === undefined || typeof item === 'function' || typeof item === 'symbol') {
        if (inArray) yield 'null'
        return
    }
    if (item === null || typeof item !== 'object' || typeof item.toJSON === 'function') {
        yield JSON.stringify(item)
        return
    }
    if (Array.isArray(item)) {
        yield '['
        for (let at = 0; at < item.length; at++) {
            if (at) yield ','
            yield* tokens(item[at], true)
        }
        yield ']'
        return
    }
    yield '{'
    let first = true
    for (const [key, each] of Object.entries(item)) {
        if (each === undefined || typeof each === 'function' || typeof each === 'symbol') continue
        if (!first) yield ','
        first = false
        yield JSON.stringify(key) + ':'
        yield* tokens(each, false)
    }
    yield '}'
}
