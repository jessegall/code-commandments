package doc_test

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

var judgesTheTree = regexp.MustCompile(`commandments judge(\s+--(no-checklist|parallel=\d+|list))*\s*$`)

var gates = []string{
	filepath.Join("..", "..", "scripts", "hooks", "pre-commit"),
	filepath.Join("..", "..", ".github", "workflows", "release.yml"),
}

func TestNoGateJudgesTheWholeTree(t *testing.T) {
	for _, gate := range gates {
		content, err := os.ReadFile(gate)
		if err != nil {
			t.Fatalf("%s is not there, so what runs by itself cannot be read: %v", gate, err)
		}
		for number, line := range strings.Split(string(content), "\n") {
			if strings.HasPrefix(strings.TrimSpace(line), "#") {
				continue
			}
			if judgesTheTree.MatchString(line) {
				t.Errorf("%s:%d judges the whole tree: %q. Scope it, or let a person ask for it", gate, number+1, strings.TrimSpace(line))
			}
		}
	}
}

func TestAJudgeOfTheWholeTreeIsRecognised(t *testing.T) {
	for _, unscoped := range []string{
		"bin/commandments judge",
		"    vendor/bin/commandments judge --no-checklist",
		"bin/commandments judge --parallel=2",
	} {
		if !judgesTheTree.MatchString(unscoped) {
			t.Errorf("%q reads as scoped, so a gate could add it unnoticed", unscoped)
		}
	}

	for _, scoped := range []string{
		"bin/commandments judge --changes",
		"bin/commandments judge --branch",
		"bin/commandments judge engine/golang --no-checklist",
		"bin/commandments judge --sin=go-loop-wrapped-in-if",
	} {
		if judgesTheTree.MatchString(scoped) {
			t.Errorf("%q reads as judging the whole tree, so the check would fail a gate that is fine", scoped)
		}
	}
}
