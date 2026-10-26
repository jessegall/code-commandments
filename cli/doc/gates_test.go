package doc_test

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

// judgesTheTree matches a judge run that is given no scope: no path, no --changes and no --branch. Such a run
// reports every finding the whole tree holds, which is the one thing a gate must never do here.
var judgesTheTree = regexp.MustCompile(`commandments judge(\s+--(no-checklist|parallel=\d+|list))*\s*$`)

// gates are the files that run by themselves when someone commits, pushes or tags: nobody chooses to run them,
// so what they do has to pass on a tree that still holds findings.
var gates = []string{
	filepath.Join("..", "..", "scripts", "hooks", "pre-commit"),
	filepath.Join("..", "..", ".github", "workflows", "release.yml"),
}

// TestNoGateJudgesTheWholeTree holds the gates to the sequencing this project judges itself under. This
// repository is a consumer of itself and its own tree still holds findings, so a gate that judged the whole of
// it would be red from the moment a language was named in the config and would stay red until the last sin was
// gone. Nothing would be learned from a check that is always failing, and the pressure would be to switch the
// rule off rather than fix the code.
//
// What judges here instead is scoped to what someone just changed: the hooks mark the file a session edited, the
// stop reminder counts that session's own files, and the worker hold reads a worker's own diff. A finding on
// code nobody touched waits until somebody does, which is what "leave it cleaner than you found it" means in
// practice. A whole-tree judgement is a thing a person asks for, never a thing that fails a commit.
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

// TestAJudgeOfTheWholeTreeIsRecognised proves the test above would notice, since a check that cannot fail says
// nothing. It is the same reading, asked of lines a gate does not hold.
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
