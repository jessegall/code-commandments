package sync

import (
	"slices"
	"strings"
	"testing"
)

func TestTheConfigIsNeverTracked(t *testing.T) {
	lines := commandmentsLines()

	for _, kept := range []string{"!config.json", "!config.php"} {
		if slices.Contains(lines, kept) {
			t.Errorf("the gitignore keeps %s tracked. The config is the journal's, and a tracked copy travels into "+
				"every worktree on a branch, where it is read instead of the real settings and cannot be told from "+
				"them", kept)
		}
	}

	if !slices.Contains(lines, "*") {
		t.Fatal("the gitignore no longer ignores everything by default, so a new file of ours would be committed")
	}
}

func TestAProjectsOwnRulesStayTracked(t *testing.T) {
	lines := strings.Join(commandmentsLines(), "\n")

	for _, kept := range []string{"!custom/", "!custom/**"} {
		if !strings.Contains(lines, kept) {
			t.Errorf("the gitignore no longer keeps %s tracked, and a project's own skills and rules are code: a "+
				"worker must be able to write one in its checkout and have it judged there", kept)
		}
	}
}

func TestTheLineThatKeptTheConfigTrackedIsRetired(t *testing.T) {
	if !slices.Contains(staleRules, "!.commandments/config.json") {
		t.Error("an older project's gitignore keeps the config tracked for ever, because nothing takes that line " +
			"out on a sync")
	}
}
