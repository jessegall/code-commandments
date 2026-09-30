package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

// TestSyntaxAtTheEdgeKeepsTheContract holds the bridge to the contract over sources that are legal but rare: a key
// that is the empty string, computed, numeric or not an identifier, in every place a name is written, and the
// declarations that have no name at all. The stream is checked against the schema as it is read, so a fact the
// bridge writes wrong fails the case that wrote it.
func TestSyntaxAtTheEdgeKeepsTheContract(t *testing.T) {
	cases := map[string]map[string]string{
		"an object literal's keys": {"marks.ts": `
export const MARKS = {"": "·", '': "x", now: "", "a b": 1, 0: 2, 1e3: 3, "é": 4, [""]: 5, ["computed" + 1]: 6}
export const read = MARKS[""] + MARKS["a b"]
`},
		"a class's members": {"members.ts": `
export class Members {
    "" = 1
    static ""() { return 2 }
    get "a b"() { return 3 }
    0() { return 4 }
    [""]() { return 5 }
    #hidden = 6
}
export const members = new Members()[""]
`},
		"a type's fields": {"types.ts": `
export interface Keys { "": string; "a b": number; 0: boolean; [key: string]: unknown }
export type Literal = { "": string, readonly "é"?: number }
export type Mapped = { [K in "" | "a"]: K }
export const keys: Keys = { "": "empty", "a b": 1, 0: true }
export const literal: Literal = { "": "x" }
export const mapped: Mapped = { "": "", a: "a" }
`},
		"an enum's members": {"enums.ts": `
export enum Mark { "" = "empty", "a b" = "spaced", plain = "plain" }
export const mark = Mark[""]
`},
		"a destructured key": {"destructure.ts": `
const source = { "": 1, "a b": 2 }
const { "": empty, "a b": spaced, ...rest } = source
export function pick({ "": first }: { "": number }) { return first + empty + spaced + Object.keys(rest).length }
`},
		"a module name that is a string": {"names.ts": `
const value = 1
export { value as "", value as "a b" }
`, "reads.ts": `
import { "" as empty, "a b" as spaced } from "./names"
export * as "" from "./names"
export const both = empty + spaced
`},
		"declarations with no name": {"anonymous.ts": `
export default class { run() { return function () { return class {} } } }
export const arrow = () => ({ "": () => "" })
`},
		"a component": {"Marks.vue": `<script setup lang="ts">
const MARKS: Record<string, string> = {"": "·", now: ""}
const props = defineProps<{ "": string, "a b"?: number }>()
const key = ""
</script>

<template>
    <div class="" :[key]="MARKS['']" :title="props['']">
        <span v-for="(mark, name) in MARKS" :key="name">{{ mark }}{{ MARKS[""] }}</span>
        <slot name="" :value="MARKS['']" />
    </div>
</template>
`},
	}
	for name, sources := range cases {
		t.Run(name, func(t *testing.T) {
			for _, file := range frontendtest.FromSource(t, sources).Files() {
				if file.Errors != 0 {
					t.Errorf("%s: %d syntax errors in a source that parses", file.Path, file.Errors)
				}
			}
		})
	}
}

// TestABrokenSourceKeepsTheContract holds the bridge to the contract over sources that do not parse: the names a
// syntax error leaves missing are written as no name, never an empty one.
func TestABrokenSourceKeepsTheContract(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"broken.ts":  "const = 1\nfunction (a) { return a. }\nclass implements {}\nconst { : x } = {}\nlet y = { : 1 }\n",
		"Broken.vue": "<script setup lang=\"ts\">\nconst = 1\nimport { } from\n</script>\n\n<template>\n    <div :title=\"a.\">{{ b. }}</div>\n</template>\n",
	})
	for _, file := range codebase.Files() {
		if file.Errors == 0 {
			t.Errorf("%s parses, so it tests nothing", file.Path)
		}
	}
}
