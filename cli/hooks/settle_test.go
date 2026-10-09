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

	found := AnnouncedIn(data, Moment{}).Settle(root, &file, marks)
	raised := found.Raises(root)
	if len(found.Found) != 1 || len(raised) != 1 || raised[0].Key != marks[0].Key(root) || len(found.Settles()) != 0 {
		t.Fatalf("the sinful edit raised %+v and settles %v", raised, found.Settles())
	}

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	repented := AnnouncedIn(data, Moment{}).Settle(root, &file, nil)
	if !slices.Equal(repented.Settles(), []string{raised[0].Key}) {
		t.Errorf("the repenting edit settles %v, want %v", repented.Settles(), raised[0].Key)
	}

	if again := AnnouncedIn(data, Moment{}).Settle(root, &file, nil); len(again.Settles()) != 0 {
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

// TestASinEditedInPlaceKeepsTheMarkItWasFoundUnder holds a sin whose own line is edited while the sin stays: the
// flagged text changes, so the sin's identity does, but it is the same sin where it was, so the edit neither repents
// it nor finds it again, and the edit that does repent it settles the key it was first raised under.
func TestASinEditedInPlaceKeepsTheMarkItWasFoundUnder(t *testing.T) {
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
	raised := AnnouncedIn(data, Moment{}).Settle(root, &file, first).Raises(root)
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
	kept := AnnouncedIn(data, Moment{}).Settle(root, &file, edited)
	if len(kept.Raises(root)) != 0 || len(kept.Settles()) != 0 {
		t.Errorf("the edit in place raised %+v and settles %v", kept.Raises(root), kept.Settles())
	}

	write("def refund(order) -> int:\n    if order.late:  # past due\n        raise ValueError(order)\n    return order.total\n")
	if repented := AnnouncedIn(data, Moment{}).Settle(root, &file, nil); !slices.Equal(repented.Settles(), []string{raised[0].Key}) {
		t.Errorf("the repenting edit settles %v, want %v", repented.Settles(), raised[0].Key)
	}
}
