// Command generate writes every skills/commandments/<slug>/ from the catalog: each skill's SKILL.md and the reference
// documents it spills into, with the worked examples its sins' fixtures carve. A skill file is a projection — never
// hand-edit it; edit the skill or the sin and generate again. Run from the repository root, in the dev container:
// scripts/dev go run ./skill/render/generate [--check]; --check writes nothing and fails on a stale file.
package main

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/fixture"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/skill"
	"github.com/jessegall/code-commandments/skill/render"
)

// published is where the generated skills live.
const published = "skills/commandments"

func main() {
	check := slices.Contains(os.Args[1:], "--check")
	examples, err := fixture.Curriculum("tests/Fixtures")
	if err != nil {
		fmt.Fprintln(os.Stderr, "✗ The skills cannot be generated:", err)
		os.Exit(1)
	}
	stale, written, err := generate(examples, check)
	if err != nil {
		fmt.Fprintln(os.Stderr, "✗", err)
		os.Exit(1)
	}
	if !check {
		fmt.Printf("SKILL.md regenerated (%d written, %d skills).\n", written, len(skill.All()))

		return
	}
	if len(stale) == 0 {
		fmt.Println("✓ All SKILL.md are current.")

		return
	}
	fmt.Fprintln(os.Stderr, "✗ Stale SKILL.md (run `composer sins`):")
	for _, file := range stale {
		fmt.Fprintln(os.Stderr, "  -", file)
	}
	os.Exit(1)
}

// generate writes each skill's documents where they differ, and removes a reference document the skill no longer
// generates — nothing else writes there, so a leftover would be published and read as current. Checking, it writes
// nothing and answers what it would have changed.
func generate(examples render.Examples, check bool) (stale []string, written int, err error) {
	for _, teaching := range skill.All() {
		slug := teaching.Definition().Slug
		folder := filepath.Join(published, slug)
		documents := render.Documents(teaching, examples)
		for name, rendered := range documents {
			path := filepath.Join(folder, name)
			if current, _ := os.ReadFile(path); string(current) == rendered {
				continue
			}
			if check {
				stale = append(stale, filepath.Join(slug, name))
				continue
			}
			if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
				return nil, written, err
			}
			if err := os.WriteFile(path, []byte(rendered), 0o644); err != nil {
				return nil, written, err
			}
			written++
		}
		leftovers, _ := filepath.Glob(filepath.Join(folder, "reference", "*.md"))
		for _, path := range leftovers {
			if _, generated := documents[filepath.Join("reference", filepath.Base(path))]; generated {
				continue
			}
			if check {
				stale = append(stale, filepath.Join(slug, "reference", filepath.Base(path))+" (orphaned)")
				continue
			}
			if err := os.Remove(path); err != nil {
				return nil, written, err
			}
			written++
		}
	}
	slices.Sort(stale)

	return stale, written, nil
}
