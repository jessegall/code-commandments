package frontend_test

import (
	"fmt"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
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

// TestASymbolKeyIsNamedAsTheCodeWritesIt holds a field whose key is a symbol to the name the code spells it with:
// TypeScript keeps such a key under a name carrying the id of the order it checked in, which no stream may write.
func TestASymbolKeyIsNamedAsTheCodeWritesIt(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"marked.ts": `
declare const marker: unique symbol
export const marked = { [marker]: 1, [Symbol.iterator]: function* () { yield 2 }, plain: 3 }
export const read = marked
`})
	names := map[string]bool{}
	var collect func(*contract.Type)
	collect = func(typed *contract.Type) {
		if typed == nil {
			return
		}
		for _, field := range typed.Fields {
			names[field.Name] = true
			collect(field.Type)
		}
		for _, each := range append(append(append([]*contract.Type{}, typed.Args...), typed.Members...), typed.Parameters...) {
			collect(each)
		}
		collect(typed.Returns)
		collect(typed.Element)
	}
	for _, file := range codebase.Files() {
		for _, node := range file.Nodes() {
			collect(node.Resolved)
		}
	}
	for _, want := range []string{"[marker]", "[Symbol.iterator]", "plain"} {
		if !names[want] {
			t.Errorf("no field is named %s among %v", want, names)
		}
	}
	for name := range names {
		if strings.HasPrefix(name, "__@") || strings.HasPrefix(name, "__#") {
			t.Errorf("a field is named by the checker's order: %s", name)
		}
	}
}

// TestALargeTypeIsPrintedShortAndDescribedWhole holds a type whose whole print runs past a kilobyte to the checker's
// truncated print, while its fields still say it whole: a library's object type printed whole, and each type inside
// it printed whole again, made a 38 KB component a 410 MB line no reader could take.
func TestALargeTypeIsPrintedShortAndDescribedWhole(t *testing.T) {
	var fields strings.Builder
	for i := range 120 {
		fmt.Fprintf(&fields, "  field%03d: { nested%03d: string; other%03d: number }\n", i, i, i)
	}
	codebase := frontendtest.FromSource(t, map[string]string{"options.ts": "export declare const typed: {\n" + fields.String() + "}\nexport const read = typed\n"})
	var widest *contract.Type
	for _, file := range codebase.Files() {
		for _, node := range file.Nodes() {
			if typed := node.Resolved; typed != nil && len(typed.Fields) == 120 {
				widest = typed
			}
		}
	}
	if widest == nil {
		t.Fatal("no type with its 120 fields was written")
	}
	if len(widest.Text) > 1024 || !strings.Contains(widest.Text, "...") {
		t.Errorf("the type's text runs %d characters: %.80s", len(widest.Text), widest.Text)
	}
	if len(widest.Fields[0].Type.Fields) != 2 {
		t.Errorf("a field's own type lost its fields: %+v", widest.Fields[0].Type)
	}
}

// TestAJavaScriptFileIsReadAsTheCompilerReadsIt holds the bridge to the files TypeScript's compiler reads beside a
// .ts: JavaScript, and the module and JSX forms, each written as TypeScript.
func TestAJavaScriptFileIsReadAsTheCompilerReadsIt(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/order-form.js": "export function total(lines) {\n  return lines.reduce((sum, line) => sum + line.price, 0)\n}\n",
		"src/config.mjs":    "export const currency = 'EUR'\n",
		"src/Cart.tsx":      "export const Cart = (props: { count: number }) => props.count\n",
	})
	read := map[string]contract.Language{}
	for _, file := range codebase.Files() {
		read[filepath.Base(file.Path)] = file.Language()
	}
	for _, name := range []string{"order-form.js", "config.mjs", "Cart.tsx"} {
		if read[name] != contract.TypeScript {
			t.Errorf("%s is read as %q, among %v", name, read[name], read)
		}
	}
}
