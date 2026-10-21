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
	if len(found.Found) != 1 || len(raised) != 1 || raised[0].Key != marks[0].Key(root) || len(found.Settles()) != 0 {
		t.Fatalf("the sinful edit raised %+v and settles %v", raised, found.Settles())
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

// TestASinEditedInPlaceIsRepentedAndFoundAfresh holds a sin whose own line is edited while the sin stays: its
// flagged text changes, so its identity does, and the rule is the plain one — every sin remembered for a file
// judged again that the judge does not find again is repented, and what it finds is raised. So the mark follows the
// text: the old one goes green and a new one opens, and the repair settles THAT one. Carrying the old mark over
// instead left a card nothing could ever settle whenever an edit went unseen between two judgings.
func TestASinEditedInPlaceIsRepentedAndFoundAfresh(t *testing.T) {
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
	// The sin that was announced is not among the sins found now, so it is repented and what IS found is raised:
	// the mark follows the text of the line it stands on. Keeping the old mark alive instead left a card nothing
	// would ever settle whenever the plugin missed the edit between two judgings.
	again := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, edited)
	if !slices.Equal(again.Settles(), []string{raised[0].Key}) {
		t.Errorf("the edit in place settles %v, want the mark it was found under, %v", again.Settles(), raised[0].Key)
	}

	raisedAgain := again.Raises(root)
	if len(raisedAgain) != 2 || raisedAgain[0].Event != "sin-found" || raisedAgain[0].Key == raised[0].Key {
		t.Fatalf("the edit in place raised %+v", raisedAgain)
	}

	write("def refund(order) -> int:\n    if order.late:  # past due\n        raise ValueError(order)\n    return order.total\n")
	if repented := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, nil); !slices.Equal(repented.Settles(), []string{raisedAgain[0].Key}) {
		t.Errorf("the repenting edit settles %v, want %v", repented.Settles(), raisedAgain[0].Key)
	}
}
