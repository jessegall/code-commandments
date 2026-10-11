package typescript

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

const registry = `export type Tag = { name: string; runs: string }

export const TAGS: Tag[] = [
  { name: 'reply', runs: 'a' },
  { name: 'log', runs: 'b' },
  { name: 'todo', runs: 'c' },
]
`

// TestASideTableOfRegisteredNamesIsFound holds the three things that together tell a side table from an ordinary
// list of words: the names are the module's own registry's, the module reaches for the objects through them, and
// a collection derived from the registry or naming none of its objects is left alone.
func TestASideTableOfRegisteredNamesIsFound(t *testing.T) {
	for name, source := range map[string]string{
		"a set asked whether it holds one": registry + `
const QUIET = new Set(['reply', 'log'])

export function said(name: string): boolean {
  return !QUIET.has(name)
}
`,
		"an array looped over": registry + `
const QUIET = ['reply', 'log']

export function hush(place: { switch(name: string, on: boolean): void }): void {
  for (const tag of QUIET) {
    place.switch(tag, false)
  }
}
`,
	} {
		t.Run(name, func(t *testing.T) {
			found := SideTableTraitDetector{}.Find(frontendtest.FromSource(t, map[string]string{"src/tags.ts": source}))

			if len(found) != 1 {
				t.Fatalf("found %d side tables, want 1: %v", len(found), locations(found))
			}
		})
	}
}

// TestACollectionThatIsNotASideTableIsLeftAloneTS holds the negatives, each wearing the same surface shape.
func TestACollectionThatIsNotASideTableIsLeftAloneTS(t *testing.T) {
	for name, source := range map[string]string{
		"ordinary vocabulary naming no object": registry + `
const SHELL_TOOLS = new Set(['exec', 'shell', 'bash'])

export function shelled(name: string): boolean {
  return SHELL_TOOLS.has(name)
}
`,
		"derived from the registry, which is the fix": `export type Tag = { name: string; runs: string; saidInChat: boolean }

export const TAGS: Tag[] = [
  { name: 'reply', runs: 'a', saidInChat: false },
  { name: 'todo', runs: 'c', saidInChat: true },
]

const QUIET = new Set(TAGS.filter((t) => !t.saidInChat).map((t) => t.name))

export function said(name: string): boolean {
  return !QUIET.has(name)
}
`,
		"held but never used as a handle": registry + `
const ORDER = ['reply', 'log']
`,
	} {
		t.Run(name, func(t *testing.T) {
			found := SideTableTraitDetector{}.Find(frontendtest.FromSource(t, map[string]string{"src/tags.ts": source}))

			if len(found) != 0 {
				t.Errorf("flagged %d, want none: %v", len(found), locations(found))
			}
		})
	}
}

func locations(found []engine.Match) []string {
	where := make([]string, 0, len(found))
	for _, match := range found {
		where = append(where, match.Location())
	}

	return where
}
