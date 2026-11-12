import { readFileSync } from 'node:fs'

/** A file's text, and the byte offset and line of every UTF-16 index in it: spans are UTF-8 bytes, JS indexes UTF-16. */
export class Source {
    constructor(path, text) {
        this.path = path
        this.text = text
    }

    /** The UTF-8 byte offset of every UTF-16 index, counted the first time a span is asked for: a file whose line is kept never pays for it. */
    get bytes() {
        this.counted()

        return this.offsets
    }

    /** The line of every UTF-16 index, counted with the byte offsets. */
    get lines() {
        this.counted()

        return this.lineOf
    }

    counted() {
        if (this.offsets) return
        const text = this.text
        this.offsets = new Uint32Array(text.length + 1)
        this.lineOf = new Uint32Array(text.length + 1)
        let mark = 0
        let line = 1
        for (let index = 0; index < text.length; index++) {
            this.offsets[index] = mark
            this.lineOf[index] = line
            const code = text.charCodeAt(index)
            mark += code < 0x80 ? 1 : code < 0x800 ? 2 : code >= 0xd800 && code <= 0xdfff ? 2 : 3
            if (code === 0x0a) line++
        }
        this.offsets[text.length] = mark
        this.lineOf[text.length] = line
    }

    static read(path) {
        return new Source(path, readFileSync(path, 'utf8'))
    }

    /** The contract's `[start, end, line]` for the UTF-16 range `[start, end)`. */
    span(start, end) {
        return [this.bytes[start], this.bytes[end], this.lines[start]]
    }

    /** Whether only whitespace stands between the start of `index`'s line and `index`. */
    leadsItsLine(index) {
        for (let at = index - 1; at >= 0; at--) {
            const char = this.text[at]
            if (char === '\n') return true
            if (char !== ' ' && char !== '\t' && char !== '\r') return false
        }

        return true
    }

    /** The index of the next character after `index` that is not whitespace. */
    skipWhitespace(index) {
        while (index < this.text.length && ' \t\r\n'.includes(this.text[index])) index++

        return index
    }
}
