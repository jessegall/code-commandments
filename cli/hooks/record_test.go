package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
)

func TestEveryJudgingWritesOneLineAPersonCanRead(t *testing.T) {
	folder := t.TempDir()

	Record(folder, Judged{
		At: time.Date(2026, 11, 6, 19, 4, 5, 0, time.UTC), Event: "file_edited",
		Checkout: "/project/.journal/worktrees/main/worker-2", Kind: "worktree", Branch: "a-worker",
		Held: false, Why: noTreeHeld, Overlay: 3, Parsed: 1, ReadMs: 412, RuleMs: 290,
		Asked: 167, NotAsked: 80, Sins: 2,
	})

	read, err := os.ReadFile(filepath.Join(folder, "judgings.log"))
	if err != nil {
		t.Fatal(err)
	}

	said := strings.TrimSpace(string(read))
	if strings.Count(said, "\n") != 0 {
		t.Errorf("one judging wrote more than one line:\n%s", said)
	}

	for _, want := range []string{
		"2026-11-06 19:04:05", "file_edited", "worktree=/project/.journal/worktrees/main/worker-2",
		"branch=a-worker", "tree=declined", "overlay=3", "parsed=1 in 412ms", "rules=167 of 247 in 290ms",
		"sins=2", "because no tree of this checkout is held",
	} {
		if !strings.Contains(said, want) {
			t.Errorf("the line does not say %q:\n%s", want, said)
		}
	}
}

func TestAJudgingThatHeldItsTreeSaysSoAndSaysNoReason(t *testing.T) {
	folder := t.TempDir()

	Record(folder, Judged{At: time.Now(), Event: "file_edited", Checkout: "/project", Kind: "project",
		Branch: "main", Held: true, Overlay: 0, Parsed: 1, ReadMs: 400, RuleMs: 290, Asked: 247, Sins: 0})

	read, _ := os.ReadFile(filepath.Join(folder, "judgings.log"))
	said := string(read)

	if !strings.Contains(said, "tree=held") || strings.Contains(said, "because") {
		t.Errorf("a judging that held its tree reads:\n%s", said)
	}
}

func TestARecordNobodyCanWriteNeverFailsAnAnswer(t *testing.T) {
	Record("", Judged{At: time.Now()})
	Record(filepath.Join(t.TempDir(), "not-a-folder", "deeper"), Judged{At: time.Now()})
}

func TestARecordSaysWhatTheJudgingDropped(t *testing.T) {
	folder := t.TempDir()

	Record(folder, Judged{At: time.Now(), Event: "file_edited", Checkout: "/project", Kind: "project",
		Branch: "main", Held: true, Parsed: 2, Asked: 247,
		Dropped: Dropped{NotJudged: 4, NotAFile: 1, TooLarge: 2, PastBudget: 3}})

	read, _ := os.ReadFile(filepath.Join(folder, "judgings.log"))
	said := string(read)

	if !strings.Contains(said, "dropped=4 outside the roots, 1 not a file, 2 too large, 3 past the budget") {
		t.Errorf("the record does not say what was dropped:\n%s", said)
	}

	plain := t.TempDir()
	Record(plain, Judged{At: time.Now(), Event: "file_edited", Checkout: "/project", Kind: "project", Branch: "main", Held: true})
	kept, _ := os.ReadFile(filepath.Join(plain, "judgings.log"))

	if strings.Contains(string(kept), "dropped=") {
		t.Errorf("a judging that dropped nothing says it did:\n%s", kept)
	}
}

func TestAMemoryFigureSaysWhichMemoryItIs(t *testing.T) {
	now := MemoryNow()

	if now.HeapMB < 0 || now.ResidentMB < 0 || now.WithBridgesMB < now.ResidentMB {
		t.Errorf("the three figures do not hold together: %+v", now)
	}

	said := now.Line()
	for _, want := range []string{"heap=", "resident=", "withBridges="} {
		if !strings.Contains(said, want) {
			t.Errorf("a memory figure does not say which memory it is: %q", said)
		}
	}
}

func TestTheRecordHoldsNoLineOfAnybodysCode(t *testing.T) {
	folder := t.TempDir()

	Record(folder, Judged{At: time.Now(), Event: "file_edited", Checkout: "/project", Kind: "project",
		Branch: "main", Held: true, Parsed: 1, Asked: 247, Sins: 3})

	read, _ := os.ReadFile(filepath.Join(folder, "judgings.log"))
	said := string(read)

	for _, code := range []string{"def ", "class ", "function ", "return ", "{", "}", ";"} {
		if strings.Contains(said, code) {
			t.Errorf("the record carries something that reads like source (%q):\n%s", code, said)
		}
	}
}

func TestTheRecordIsOnUnlessTheSettingSaysOtherwise(t *testing.T) {
	t.Setenv("JOURNAL_SETTINGS", `{"judge_record": "false"}`)
	if recorded("/project") {
		t.Error("the record is kept although the setting says not to")
	}

	t.Setenv("JOURNAL_SETTINGS", `{"judge_record": "true"}`)
	if !recorded("/project") {
		t.Error("the record is not kept although the setting says to")
	}

	t.Setenv("JOURNAL_SETTINGS", "")
	if !recorded("/project") {
		t.Error("with no settings at all the record is not kept, so a person at two in the morning has nothing to read")
	}
}
