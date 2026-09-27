package main

import (
	"os"
	"testing"

	"github.com/jessegall/code-commandments/fixture"
)

// TestTheSkillsAreGeneratedFromTodaysSources holds every published skill to what the catalog and the fixtures
// generate today: a skill or sin changed without generating again leaves its SKILL.md teaching the old rule.
func TestTheSkillsAreGeneratedFromTodaysSources(t *testing.T) {
	if err := os.Chdir("../../.."); err != nil {
		t.Fatal(err)
	}
	examples, err := fixture.Curriculum("tests/Fixtures")
	if err != nil {
		t.Skip(err)
	}
	stale, _, err := generate(examples, true)
	if err != nil {
		t.Fatal(err)
	}
	for _, file := range stale {
		t.Errorf("%s is stale: scripts/dev go run ./skill/render/generate", file)
	}
}
