package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
)

func TestLanesSaysWhichTreesAreWarmAndHowFarARunningReadHasGot(t *testing.T) {
	root, _ := workerProject(t)

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	read := &treeRead{project: project, branch: "feature/other", began: time.Now()}
	read.reached(80, 200)
	reading.Store(project+"\x00feature/other", read)
	t.Cleanup(func() { reading.Delete(project + "\x00feature/other") })

	said := lanesHeld("")

	for _, want := range []string{branch + " | warm | 2 file(s)", "held since", "feature/other | warming 40%", "80 of 200 file(s) read"} {
		if !strings.Contains(said, want) {
			t.Errorf("lanes does not say %q, so nobody can tell a warm tree from one still being read:\n%s", want, said)
		}
	}

	if label := lanesLabel(); !strings.HasPrefix(label, "judge lanes: 1 tree(s) warm, 1 warming 40%") {
		t.Errorf("the card's one line is %q, which does not lead with the warm trees and the read in progress", label)
	}
}

func TestLanesUnderTheJournalShowsWhatItSaysAsACardInTheChat(t *testing.T) {
	folder := t.TempDir()
	asked := filepath.Join(folder, "asked")
	journal := filepath.Join(folder, "journal")
	if err := os.WriteFile(journal, []byte("#!/bin/sh\nprintf '%s\\n' \"$@\" > "+asked+"\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("JOURNAL_BIN", journal)

	lanesAnswer{tell: "1 tree(s) held, 0 being read", label: "judge lanes: 1 tree(s) warm, 0 lane(s)"}.shownInTheChat()

	said, err := os.ReadFile(asked)
	if err != nil {
		t.Fatalf("lanes under the journal posted no card: %v", err)
	}

	want := "plugin\ncard\ncode-commandments\n--label\njudge lanes: 1 tree(s) warm, 0 lane(s)\n--detail\n1 tree(s) held, 0 being read\n--tone\nnote\n"
	if string(said) != want {
		t.Errorf("the card was asked for as:\n%s\nwant:\n%s", said, want)
	}
}

func TestEachLaneSaysHowAJudgingThereWouldBeAnswered(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")

	if said := answeredHow(worktree); !strings.HasPrefix(said, "answered smaller") {
		t.Errorf("with no tree held, a judging of the worker's checkout is said to be %q", said)
	}

	project := git.ProjectRoot(root)
	branch := git.CurrentBranch(project)

	settings, _ := config.Load(root)
	codebase, err := scan.Walk([]string{filepath.Join(root, "src")}, source.Under(root, settings.Excluded)).Load()
	if err != nil || codebase == nil {
		t.Fatalf("the python bridge cannot run here: %v", err)
	}

	kept.Hold(project, branch, []string{root}, codebase, nil)
	t.Cleanup(func() { kept.Drop(project, branch) })

	if said := answeredHow(root); said != "answered from the tree" {
		t.Errorf("with the tree held, a judging of the project is said to be %q", said)
	}
}
