package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
)

func TestWhyReadsTheRecordOfTheLastJudgings(t *testing.T) {
	folder := t.TempDir()
	t.Setenv(pluginData, folder)

	Record(folder, Judged{At: time.Now(), Event: "file_edited", Checkout: "/project", Kind: "project",
		Branch: "main", Held: true, Parsed: 1, ReadMs: 400, RuleMs: 290, Asked: 247, Sins: 0})
	Record(folder, Judged{At: time.Now(), Event: "file_edited", Checkout: "/project/.journal/worktrees/main/worker-2",
		Kind: "worktree", Branch: "a-worker", Held: false, Why: noTreeHeld, Overlay: 3, Parsed: 1,
		ReadMs: 412, RuleMs: 290, Asked: 167, NotAsked: 80, Sins: 2})

	said := &strings.Builder{}
	if code, err := (Why{}).Run(cli.InputOf("why", "worker-2"), cli.Console{Out: said, Err: said}); code != 0 || err != nil {
		t.Fatalf("why answered %d: %v", code, err)
	}

	if strings.Count(strings.TrimSpace(said.String()), "\n") != 0 {
		t.Errorf("asking about one checkout answered about every one:\n%s", said)
	}

	for _, want := range []string{"worker-2", "tree=declined", "rules=167 of 247", "because no tree"} {
		if !strings.Contains(said.String(), want) {
			t.Errorf("the answer does not say %q:\n%s", want, said)
		}
	}

	every := &strings.Builder{}
	(Why{}).Run(cli.InputOf("why"), cli.Console{Out: every, Err: every})
	if strings.Count(strings.TrimSpace(every.String()), "\n") != 1 {
		t.Errorf("asking about nothing in particular did not answer about both judgings:\n%s", every)
	}
}

func TestWhyAboutAFileNobodyJudgedSaysThatItIsNotClean(t *testing.T) {
	folder := t.TempDir()
	t.Setenv(pluginData, folder)

	Record(folder, Judged{At: time.Now(), Event: "file_edited", Checkout: "/project", Kind: "project", Branch: "main", Held: true})

	said := &strings.Builder{}
	(Why{}).Run(cli.InputOf("why", "src/never-judged.py"), cli.Console{Out: said, Err: said})

	if !strings.Contains(said.String(), "not a file with no sins") {
		t.Errorf("a file nobody judged reads as judged:\n%s", said)
	}
}

func TestWhyWithNoRecordSaysWhereItLooked(t *testing.T) {
	folder := t.TempDir()
	t.Setenv(pluginData, folder)

	said := &strings.Builder{}
	code, _ := (Why{}).Run(cli.InputOf("why"), cli.Console{Out: said, Err: said})

	if code != 2 || !strings.Contains(said.String(), filepath.Join(folder, "judgings.log")) {
		t.Errorf("with no record at all why answered %d:\n%s", code, said)
	}

	_ = os.Remove(filepath.Join(folder, "judgings.log"))
}
