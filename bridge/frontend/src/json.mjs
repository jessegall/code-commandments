/** How much of a line is held before it is handed on. */
const CHUNK = 1 << 20

/**
 * The pieces of `value` as one JSON line, made as they are taken, byte for byte as `JSON.stringify(value) + '\n'`
 * writes it: a generated file's tree is a line of a hundred megabytes, too large to hold as one string beside itself.
 */
export function* lineOf(value) {
    let held = ''
    const pending = [{ item: value, inArray: false }]
    while (pending.length) {
        const next = pending.pop()
        if (typeof next === 'string') {
            held += next
        } else {
            held += written(next.item, next.inArray, pending)
        }
        if (held.length >= CHUNK) {
            yield held
            held = ''
        }
    }
    yield held + '\n'
}

/**
 * The JSON text `item` opens with, its members left on `pending` to be written after it, last first so they come
 * off in order; `inArray` says whether an unwritable value stands as `null`. One loop over an explicit stack, where a
 * generator per level would hand every token up through every level above it.
 */
function written(item, inArray, pending) {
    if (item === undefined || typeof item === 'function' || typeof item === 'symbol') return inArray ? 'null' : ''
    if (item === null || typeof item !== 'object' || typeof item.toJSON === 'function') return JSON.stringify(item)
    if (Array.isArray(item)) {
        pending.push(']')
        for (let at = item.length - 1; at >= 0; at--) {
            pending.push({ item: item[at], inArray: true })
            if (at) pending.push(',')
        }

        return '['
    }
    const entries = Object.entries(item).filter(([, each]) => each !== undefined && typeof each !== 'function' && typeof each !== 'symbol')
    pending.push('}')
    for (let at = entries.length - 1; at >= 0; at--) {
        const [key, each] = entries[at]
        pending.push({ item: each, inArray: false })
        pending.push((at ? ',' : '') + JSON.stringify(key) + ':')
    }

    return '{'
}
