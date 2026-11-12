package judge

import (
	"os"
	"path/filepath"
	"reflect"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func weighingsWith(t *testing.T, kept map[string]weighing, changed bool) {
	t.Helper()

	weighingsHeld.Lock()
	before, was := weighings, weighingsChanged
	weighings, weighingsChanged = kept, changed
	weighingsHeld.Unlock()

	t.Cleanup(func() {
		weighingsHeld.Lock()
		weighings, weighingsChanged = before, was
		weighingsHeld.Unlock()
	})
}

func savedAfterOneJudging() map[string]weighing {
	return map[string]weighing{"duplicate-csharp-method\x00ffffffffffffffff": {
		Print:  18446744073709551615,
		Folder: "/one",
		Findings: []engine.Finding{
			{Detector: "duplicate-csharp-method", File: "/one/a.cs", Line: 3, Location: "/one/a.cs:3", Twins: []string{"/one/b.cs:9"}},
		},
	}}
}

func TestWhatCSharpRulesFoundIsKeptAcrossARestart(t *testing.T) {
	file := WeighingsFile{Path: filepath.Join(t.TempDir(), "csharp-weighings.json"), Build: "v1", Warn: func(...string) {}}
	kept := savedAfterOneJudging()

	weighingsWith(t, kept, true)
	file.Save()

	weighingsWith(t, map[string]weighing{}, false)
	file.Load()

	if !reflect.DeepEqual(weighings, kept) {
		t.Fatalf("after a restart the service holds %+v, where it saved %+v", weighings, kept)
	}
}

func TestWhatAnotherBuildSavedIsNotRead(t *testing.T) {
	path := filepath.Join(t.TempDir(), "csharp-weighings.json")

	weighingsWith(t, savedAfterOneJudging(), true)
	WeighingsFile{Path: path, Build: "v1", Warn: func(...string) {}}.Save()

	weighingsWith(t, map[string]weighing{}, false)
	WeighingsFile{Path: path, Build: "v2", Warn: func(...string) {}}.Load()

	if len(weighings) != 0 {
		t.Fatalf("a build read the findings of another build's rules: %+v", weighings)
	}
}

func TestFindingsThatDidNotChangeAreNotWrittenAgain(t *testing.T) {
	path := filepath.Join(t.TempDir(), "csharp-weighings.json")

	weighingsWith(t, savedAfterOneJudging(), false)
	WeighingsFile{Path: path, Build: "v1", Warn: func(...string) {}}.Save()

	if _, err := os.Stat(path); err == nil {
		t.Fatal("findings no judging changed were written to the file")
	}
}

func TestAFileThatCannotBeReadIsToldAndStartsEmpty(t *testing.T) {
	path := filepath.Join(t.TempDir(), "csharp-weighings.json")
	if err := os.WriteFile(path, []byte("{not json"), 0o644); err != nil {
		t.Fatal(err)
	}

	var told []string

	weighingsWith(t, map[string]weighing{}, false)
	WeighingsFile{Path: path, Build: "v1", Warn: func(said ...string) { told = append(told, said...) }}.Load()

	if len(told) != 1 || len(weighings) != 0 {
		t.Fatalf("an unreadable file was told as %v and left %+v", told, weighings)
	}
}

func TestTwoCheckoutsWithDifferentCSharpKeepBothWeighings(t *testing.T) {
	file := WeighingsFile{Path: filepath.Join(t.TempDir(), "csharp-weighings.json"), Build: "v1", Warn: func(...string) {}}
	one := weighing{Print: 1, Folder: "/one", Used: 1}
	other := weighing{Print: 2, Folder: "/other", Used: 2}

	weighingsWith(t, map[string]weighing{"duplicate-csharp-method\x001": one}, true)
	file.Save()

	weighingsWith(t, map[string]weighing{"duplicate-csharp-method\x002": other}, true)
	file.Save()

	weighingsWith(t, map[string]weighing{}, false)
	file.Load()

	if want := map[string]weighing{"duplicate-csharp-method\x001": one, "duplicate-csharp-method\x002": other}; !reflect.DeepEqual(weighings, want) {
		t.Fatalf("the file holds %+v, where both checkouts' weighings %+v belong", weighings, want)
	}
}

func TestOnlyTheNewestWeighingsOfARuleAreKept(t *testing.T) {
	held := map[string]weighing{}
	for print := range weighingsKeptPerRule + 2 {
		held["duplicate-csharp-method\x00"+string(rune('a'+print))] = weighing{Used: int64(print)}
	}

	newestPerRule(held)

	if len(held) != weighingsKeptPerRule {
		t.Fatalf("a rule keeps %d weighings, want %d", len(held), weighingsKeptPerRule)
	}
	if _, oldest := held["duplicate-csharp-method\x00a"]; oldest {
		t.Error("the weighing used longest ago was kept")
	}
}
