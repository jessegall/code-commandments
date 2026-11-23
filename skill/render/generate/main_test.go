package main

import (
	"os"
	"strings"
	"testing"
)

func TestMain(m *testing.M) {
	if err := os.Chdir("../../.."); err != nil {
		panic(err)
	}
	os.Exit(m.Run())
}

func TestTheSkillsAreGeneratedFromTodaysSources(t *testing.T) {
	stale, _, err := generate(published, true)
	if err != nil {
		t.Fatal(err)
	}
	for _, file := range stale {
		t.Errorf("%s is stale: scripts/dev go run ./skill/render/generate", file)
	}
}

func TestStaleDocumentsAreNamedInOrder(t *testing.T) {
	stale, _, err := generate(t.TempDir(), true)
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
