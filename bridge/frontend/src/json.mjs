/** How much of a line is held before it is written. */
const CHUNK = 1 << 20

/**
 * Writes `value` as one JSON line through `out`, a piece at a time, byte for byte as `JSON.stringify(value) + '\n'`
 * writes it: a generated file's tree is a line of a hundred megabytes, too large to hold as one string beside itself.
 */
export function writeLine(value, out) {
    let held = ''
    const put = (text) => {
        held += text
        if (held.length >= CHUNK) {
            out(held)
            held = ''
        }
    }
    const walk = (item, inArray) => {
        if (item === undefined || typeof item === 'function' || typeof item === 'symbol') {
            put(inArray ? 'null' : '')
            return
        }
        if (item === null || typeof item !== 'object' || typeof item.toJSON === 'function') {
            put(JSON.stringify(item))
            return
        }
        if (Array.isArray(item)) {
            put('[')
            item.forEach((each, at) => {
                if (at) put(',')
                walk(each, true)
            })
            put(']')
            return
        }
        put('{')
        let first = true
        for (const [key, each] of Object.entries(item)) {
            if (each === undefined || typeof each === 'function' || typeof each === 'symbol') continue
            if (!first) put(',')
            first = false
            put(JSON.stringify(key) + ':')
            walk(each, false)
        }
        put('}')
    }
    walk(value, false)
    put('\n')
    out(held)
}
