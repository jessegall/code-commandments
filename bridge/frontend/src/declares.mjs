import ts from 'typescript'
import { KINDS } from './typescript.mjs'

/**
 * What this bridge can emit, per language it writes, as the contract's one ask besides a tree reads it.
 *
 * Both answers come from this bridge's OWN writer tables, not from the compiler's full vocabulary, and say so:
 * a TypeScript kind is whatever name `ts.SyntaxKind` gives the node, which is the compiler's, but a field is the
 * node object's own property name, read off the parent at the moment the child is written, so nothing enumerates
 * the fields ahead of time and none are declared. Vue's tree is this bridge's invention from end to end, so both
 * its kinds and its fields are declared exactly.
 */
export function declarations() {
    return [typescript(), vue()]
}

function typescript() {
    const kinds = [...new Set(Object.values(KINDS))].sort().map((kind) => ({ kind }))

    return {
        language: 'typescript',
        parser: `typescript ${ts.version}`,
        from: "this bridge's own kind table over ts.SyntaxKind; a field is the parent node's own property name, read as the child is written, so none are declared",
        kinds,
    }
}

// The Vue tree as this bridge writes it: each kind with the slots its children fill. It is declared beside the
// writer because it IS the writer's table - vue.mjs writes these kinds and no others.
const VUE = {
    Component: ['blocks'],
    Block: ['attributes', 'children'],
    Element: ['attributes', 'children'],
    Text: [],
    Interpolation: ['children'],
    Attribute: [],
    Directive: ['alias', 'arg', 'iterable', 'value'],
    Identifier: [],
}

function vue() {
    // A `.vue` file's tree really holds both: the markup this bridge invents, and the whole TypeScript tree of
    // each `<script>` block and each template expression. So the Vue vocabulary is its own kinds AND
    // TypeScript's, because a rule selecting inside a Vue file selects among all of them.
    const own = Object.entries(VUE).map(([kind, fields]) => (fields.length ? { kind, fields } : { kind }))
    const borrowed = [...new Set(Object.values(KINDS))].filter((kind) => !(kind in VUE)).map((kind) => ({ kind }))

    return {
        language: 'vue',
        parser: `@vue/compiler-sfc with typescript ${ts.version}`,
        from: "this bridge's own writer table in vue.mjs for the markup, and its kind table over ts.SyntaxKind for everything a <script> block or a template expression holds, since a block's content is one TypeScript SourceFile among its children",
        kinds: [...own, ...borrowed].sort((one, other) => one.kind.localeCompare(other.kind)),
    }
}
