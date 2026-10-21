package typescript

import (
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

// TestAWalkThatChangesWhatItWalksIsFoundAndACopyIsNot holds the rule to the fault it names: a walk reads a
// collection as it stood when it began, so changing that collection under it skips an element or reads one twice.
// A copy is a different array and says so, a change to another collection is the fix, and a change that ends the
// walk where it stands is read once and safely.
func TestAWalkThatChangesWhatItWalksIsFoundAndACopyIsNot(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a splice inside the walk of it": {
			"export function prune(lines: string[]): void {\n    for (const line of lines) {\n        if (line === '') {\n            lines.splice(lines.indexOf(line), 1)\n        }\n    }\n}\n",
			1,
		},
		"a shift out of the walk of it": {
			"export function drain(items: number[]): number {\n    let total = 0\n    for (const item of items) {\n        total += item\n        items.shift()\n    }\n\n    return total\n}\n",
			1,
		},
		"a sort under the walk of it": {
			"export function ordered(items: number[]): void {\n    for (const item of items) {\n        if (item < 0) {\n            items.sort()\n        }\n    }\n}\n",
			1,
		},
		"a walk that grows as it goes": {
			"export function closure(ids: Set<string>, boundTo: (id: string) => string[]): void {\n    for (const id of ids) {\n        for (const bound of boundTo(id)) {\n            ids.add(bound)\n        }\n    }\n}\n",
			0,
		},
		"a prune of the set it walks": {
			"export function prune(names: Set<string>): void {\n    for (const name of names) {\n        if (name.startsWith('_')) {\n            names.delete(name)\n        }\n    }\n}\n",
			0,
		},
		"a write back of the entry the walk holds": {
			"export function fold(rows: Map<string, number>): void {\n    for (const [id, count] of rows) {\n        rows.set(id, count + 1)\n    }\n}\n",
			0,
		},
		"a forEach that splices what it walks": {
			"export function prune(lines: string[]): void {\n    lines.forEach((line) => {\n        if (line === '') {\n            lines.splice(lines.indexOf(line), 1)\n        }\n    })\n}\n",
			1,
		},
		"a forEach whose callback returns first": {
			"export function prune(lines: string[]): void {\n    lines.forEach((line) => {\n        if (line !== '') {\n            return\n        }\n        lines.splice(lines.indexOf(line), 1)\n    })\n}\n",
			1,
		},
		"a walk of a spread copy": {
			"export function prune(lines: string[]): void {\n    for (const line of [...lines]) {\n        if (line === '') {\n            lines.splice(lines.indexOf(line), 1)\n        }\n    }\n}\n",
			0,
		},
		"a walk of a sliced copy": {
			"export function prune(lines: string[]): void {\n    for (const line of lines.slice()) {\n        lines.splice(lines.indexOf(line), 1)\n    }\n}\n",
			0,
		},
		"a change to another collection": {
			"export function split(lines: string[], empty: string[]): void {\n    for (const line of lines) {\n        if (line === '') {\n            empty.push(line)\n        }\n    }\n}\n",
			0,
		},
		"a change that ends the walk": {
			"export function dropFirst(lines: string[]): void {\n    for (const line of lines) {\n        if (line === '') {\n            lines.splice(lines.indexOf(line), 1)\n            break\n        }\n    }\n}\n",
			0,
		},
		"a change written in the subject": {
			"export function sorted(lines: string[]): void {\n    for (const line of lines.sort()) {\n        console.log(line)\n    }\n}\n",
			0,
		},
		"a filter that answers a new array": {
			"export function pruned(lines: string[]): string[] {\n    const kept: string[] = []\n    for (const line of lines) {\n        if (line !== '') {\n            kept.push(line)\n        }\n    }\n\n    return kept\n}\n",
			0,
		},
	} {
		t.Run(name, func(t *testing.T) {
			found := CollectionMutatedWhileIteratedDetector{}.Find(frontendtest.FromSource(t, map[string]string{"src/lines.ts": test.source}))

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
