package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	pydetectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestARepentedSinSettlesTheMarkItWasFoundUnder holds a sin's chat mark to its whole life: the edit that commits
// the sin raises it under a key, and the later edit that repents it settles that same key.
func TestARepentedSinSettlesTheMarkItWasFoundUnder(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	codebase := pythontest.FromSource(t, map[string]string{"shop/refund.py": sinful})
	file := pythontest.File(t, codebase, "shop/refund.py").Path
	root, data := filepath.Dir(filepath.Dir(file)), t.TempDir()

	rule := pydetectors.RedundantElseDetector{}
	var marks []SinMark
	for _, match := range rule.Find(codebase) {
		marks = append(marks, SinMark{rule, match, true})
	}

	found := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, marks)
	raised := found.Raises(root)
	if len(found.Found) != 1 || len(raised) != 1 || raised[0].Key != "sins:shop/refund.py" || len(found.Settles()) != 0 {
		t.Fatalf("the sinful edit raised %+v and settles %v", raised, found.Settles())
	}

	if raised[0].Brief != "1 sin in shop/refund.py" || raised[0].Detail != marks[0].ShownFrom(root) {
		t.Errorf("the file's mark reads %q, listing %q", raised[0].Brief, raised[0].Detail)
	}

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	repented := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, nil)
	if !slices.Equal(repented.Settles(), []string{raised[0].Key}) {
		t.Errorf("the repenting edit settles %v, want %v", repented.Settles(), raised[0].Key)
	}

	if again := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, nil); len(again.Settles()) != 0 {
		t.Errorf("a sin repented once is settled again: %v", again.Settles())
	}
}

// TestAWriteThatRepentsASinSettlesItThroughTheHook holds the whole path a journal moment takes: a Write that commits
// a sin raises it under its key, and the Write that takes it out raises it repented and settles that key.
func TestAWriteThatRepentsASinSettlesItThroughTheHook(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	t.Setenv(pluginData, t.TempDir())
	file := filepath.Join(root, "src", "probe.py")
	for path, text := range map[string]string{".commandments/config.json": `{"paths": ["src"]}`, "src/probe.py": ""} {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	if out, err := exec.Command("git", "-C", root, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("%s", out)
	}

	write := func(text string) JournalAnswer {
		if err := os.WriteFile(file, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}

		return answerWith(map[string]any{"event": "hook.PostToolUse", "project": root, "env": "main", "tool": map[string]any{"name": "Write", "file": file}}, every, true)
	}

	sinful := write("def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n")
	if len(sinful.Raises) != 1 || sinful.Raises[0].Event != "sin-found" || sinful.Raises[0].Key == "" {
		t.Fatalf("the sinful write raised %+v", sinful.Raises)
	}

	righteous := write("def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n")
	if len(righteous.Raises) != 1 || righteous.Raises[0].Event != "sin-resolved" || !slices.Equal(righteous.Settles, []string{sinful.Raises[0].Key}) {
		t.Errorf("the righteous write raised %+v and settles %v", righteous.Raises, righteous.Settles)
	}
}

// TestASinEditedInPlaceKeepsTheFilesOneMark holds a sin whose own line is edited while the sin stays. Its identity
// changes, so the record repents what it knew and takes up what it found; the chat does not move, because its mark
// is the file's and the file still holds a sin. The mark is rewritten with what the file holds now, and settles
// only when the file holds nothing.
func TestASinEditedInPlaceKeepsTheFilesOneMark(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	codebase := pythontest.FromSource(t, map[string]string{"shop/refund.py": sinful})
	file := pythontest.File(t, codebase, "shop/refund.py").Path
	root, data := filepath.Dir(filepath.Dir(file)), t.TempDir()
	rule := pydetectors.RedundantElseDetector{}
	marksIn := func(codebase *engine.Codebase) []SinMark {
		var marks []SinMark
		for _, match := range rule.Find(codebase) {
			marks = append(marks, SinMark{rule, match, true})
		}

		return marks
	}
	write := func(source string) {
		if err := os.WriteFile(file, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	first := marksIn(codebase)
	firstID := first[0].ID(root)
	raised := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, first).Raises(root)
	if len(raised) != 1 {
		t.Fatalf("the sinful edit raised %+v", raised)
	}

	write("def refund(order) -> int:\n    if order.late:  # past due\n        raise ValueError(order)\n    else:\n        return order.total\n")
	stream, err := bridge.Once(bridgetest.Mypy(t), root)
	if err != nil {
		t.Fatal(err)
	}
	edited := marksIn(engine.Load(stream))
	if len(edited) != 1 || edited[0].ID(root) == firstID {
		t.Fatalf("the edit in place did not change the sin's identity: %v", edited)
	}
	// The sin's identity changed, so the record repents the one it knew and takes up the one it found — but the
	// chat's mark is the FILE's, and the file still holds a sin, so the mark is rewritten where it stands and
	// nothing is settled. One card, one file, from the first sin in it to the last.
	again := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, edited)
	if len(again.Settles()) != 0 {
		t.Errorf("the edit in place settled %v while the file still holds a sin", again.Settles())
	}

	raisedAgain := again.Raises(root)
	if len(raisedAgain) != 1 || raisedAgain[0].Key != raised[0].Key || raisedAgain[0].Detail != edited[0].ShownFrom(root) {
		t.Fatalf("the edit in place raised %+v", raisedAgain)
	}

	write("def refund(order) -> int:\n    if order.late:  # past due\n        raise ValueError(order)\n    return order.total\n")
	if repented := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, nil); !slices.Equal(repented.Settles(), []string{raised[0].Key}) {
		t.Errorf("the repenting edit settles %v, want %v", repented.Settles(), raised[0].Key)
	}
}
