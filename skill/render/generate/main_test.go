package main

import (
	"os"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/fixture"
)

// TestMain runs the tests from the repository's root, where the fixtures and the published skills are.
func TestMain(m *testing.M) {
	if err := os.Chdir("../../.."); err != nil {
		panic(err)
	}
	os.Exit(m.Run())
}

// TestTheSkillsAreGeneratedFromTodaysSources holds every published skill to what the catalog and the fixtures
// generate today: a skill or sin changed without generating again leaves its SKILL.md teaching the old rule.
func TestTheSkillsAreGeneratedFromTodaysSources(t *testing.T) {
	examples, err := fixture.Curriculum("tests/Fixtures")
	if err != nil {
		t.Skip(err)
	}
	stale, _, err := generate(examples, published, true)
	if err != nil {
		t.Fatal(err)
	}
	for _, file := range stale {
		t.Errorf("%s is stale: scripts/dev go run ./skill/render/generate", file)
	}
}

// TestStaleDocumentsAreNamedInOrder holds the --check report to one order: into an empty folder every document
// is stale, and each skill's are named in name order, its SKILL.md first.
func TestStaleDocumentsAreNamedInOrder(t *testing.T) {
	examples, err := fixture.Curriculum("tests/Fixtures")
	if err != nil {
		t.Skip(err)
	}
	stale, _, err := generate(examples, t.TempDir(), true)
	if err != nil {
		t.Fatal(err)
	}
	if len(stale) == 0 {
		t.Fatal("an empty folder holds no stale document")
	}
	previous := ""
	for _, file := range stale {
		if strings.HasSuffix(file, "/SKILL.md") {
			previous = file
			continue
		}
		if file < previous {
			t.Errorf("%s is named after %s", file, previous)
		}
		previous = file
	}
}
