package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/cli/source"
	pydetectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

func TestARepentedSinSettlesTheMarkItWasFoundUnder(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	codebase := pythontest.FromSource(t, map[string]string{"shop/refund.py": sinful})
	file := pythontest.File(t, codebase, "shop/refund.py").Path
	root, data := filepath.Dir(filepath.Dir(file)), t.TempDir()

	rule := pydetectors.RedundantElseDetector{}
	var marks []SinMark
	for _, match := range rule.Find(codebase) {
		marks = append(marks, SinMark{Rule: rule, Match: match, Touched: true})
	}

	found := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, marks)
	raised := found.Raises(root)
	if len(found.Found) != 1 || len(raised) != 1 || raised[0].Key != "sins:shop/refund.py" || len(found.Settles()) != 0 {
		t.Fatalf("the sinful edit raised %+v and settles %v", raised, found.Settles())
	}

	says := marks[0].Rule.Sin().Definition().Description
	if raised[0].Brief != "1 sin in shop/refund.py" || raised[0].Detail != "redundant-python-else at line 2\n"+says {
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

func TestASinEditedInPlaceKeepsTheFilesOneMark(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	codebase := pythontest.FromSource(t, map[string]string{"shop/refund.py": sinful})
	file := pythontest.File(t, codebase, "shop/refund.py").Path
	root, data := filepath.Dir(filepath.Dir(file)), t.TempDir()
	rule := pydetectors.RedundantElseDetector{}
	marksIn := func(codebase *engine.Codebase) []SinMark {
		var marks []SinMark
		for _, match := range rule.Find(codebase) {
			marks = append(marks, SinMark{Rule: rule, Match: match, Touched: true})
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
	again := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, edited)
	if len(again.Settles()) != 0 {
		t.Errorf("the edit in place settled %v while the file still holds a sin", again.Settles())
	}

	raisedAgain := again.Raises(root)
	named := edited[0].Rule.Sin().Definition().Name + " at line " + strconv.Itoa(edited[0].Match.Line()) +
		"\n" + edited[0].Rule.Sin().Definition().Description
	if len(raisedAgain) != 1 || raisedAgain[0].Key != raised[0].Key || raisedAgain[0].Detail != named {
		t.Fatalf("the edit in place raised %+v", raisedAgain)
	}

	write("def refund(order) -> int:\n    if order.late:  # past due\n        raise ValueError(order)\n    return order.total\n")
	if repented := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, nil); !slices.Equal(repented.Settles(), []string{raised[0].Key}) {
		t.Errorf("the repenting edit settles %v, want %v", repented.Settles(), raised[0].Key)
	}
}

func TestAFilesMarkNamesItsSinsAndTheirLines(t *testing.T) {
	source := strings.Repeat("\n", 6) + "a = 1\nb = 2\n" + strings.Repeat("\n", 43) + "c = 3\nd = 4\n"
	codebase := pythontest.FromSource(t, map[string]string{"shop/orders.py": source})
	file := pythontest.File(t, codebase, "shop/orders.py")
	root, data := filepath.Dir(filepath.Dir(file.Path)), t.TempDir()

	at := map[int]engine.Match{}
	for _, match := range codebase.WhereAssign().Get() {
		at[match.Line()] = match
	}

	for _, line := range []int{7, 8, 52, 53} {
		if _, found := at[line]; !found {
			t.Fatalf("the fixture has no assignment on line %d, so the lines it names are not the ones meant", line)
		}
	}

	rule, other := pydetectors.RedundantElseDetector{}, pydetectors.MutableDefaultDetector{}
	marks := []SinMark{
		{Rule: other, Match: at[52], Touched: true},
		{Rule: rule, Match: at[53], Touched: true},
		{Rule: rule, Match: at[7], Touched: true},
		{Rule: rule, Match: at[8], Touched: true},
	}

	raised := AnnouncedIn(data, Moment{}).Settle(root, []string{file.Path}, marks).Raises(root)
	if len(raised) != 1 {
		t.Fatalf("one file raised %d marks", len(raised))
	}

	want := "redundant-python-else at lines 7, 8, 53\npython-mutable-default at line 52"
	if raised[0].Detail != want {
		t.Errorf("the mark's lines read %q, want %q", raised[0].Detail, want)
	}
}

func TestAMarkWithOneSinStillOpens(t *testing.T) {
	codebase := pythontest.FromSource(t, map[string]string{"shop/orders.py": "a = 1\n"})
	file := pythontest.File(t, codebase, "shop/orders.py")
	root, data := filepath.Dir(filepath.Dir(file.Path)), t.TempDir()

	rule := pydetectors.RedundantElseDetector{}
	mark := SinMark{Rule: rule, Match: codebase.WhereAssign().Get()[0], Touched: true}

	raised := AnnouncedIn(data, Moment{}).Settle(root, []string{file.Path}, []SinMark{mark}).Raises(root)
	lines := strings.Split(raised[0].Detail, "\n")

	if len(lines) != 2 {
		t.Fatalf("the one-sin mark reads %q, which opens only at two lines or more", raised[0].Detail)
	}

	if lines[0] != "redundant-python-else at line 1" || lines[1] != rule.Sin().Definition().Description {
		t.Errorf("the one-sin mark reads %q", raised[0].Detail)
	}
}

func TestAPartialJudgingNeverCallsAFileClean(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	codebase := pythontest.FromSource(t, map[string]string{"shop/refund.py": sinful})
	file := pythontest.File(t, codebase, "shop/refund.py").Path
	root, data := filepath.Dir(filepath.Dir(file)), t.TempDir()
	named := source.Relative(root, file)

	rule := pydetectors.RedundantElseDetector{}
	var marks []SinMark
	for _, match := range rule.Find(codebase) {
		marks = append(marks, SinMark{Rule: rule, Match: match, Touched: true})
	}
	if len(marks) != 1 {
		t.Fatalf("the fixture holds %d sins, want 1", len(marks))
	}

	raised := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, marks).Raises(root)
	if len(raised) != 1 {
		t.Fatalf("the sinful edit raised %+v", raised)
	}

	narrow := Asked{named: {"python-invented-default", "python-blank-string-default"}}
	partial := AnnouncedIn(data, Moment{}).SettleAsked(root, []string{file}, nil, narrow)

	if settles := partial.Settles(); len(settles) != 0 {
		t.Errorf("a judging that never asked about the sin settled its mark: %v", settles)
	}
	if len(partial.Repented) != 0 {
		t.Errorf("a judging that never asked about the sin called it repented: %+v", partial.Repented)
	}

	again := AnnouncedIn(data, Moment{}).Settle(root, []string{file}, marks)
	if len(again.Found) != 0 {
		t.Errorf("the sin was announced again as new: %+v", again.Found)
	}

	whole := Asked{named: {"redundant-python-else"}}
	repented := AnnouncedIn(data, Moment{}).SettleAsked(root, []string{file}, nil, whole)
	if !slices.Equal(repented.Settles(), []string{raised[0].Key}) {
		t.Errorf("a judging that asked about the sin and found it gone settles %v, want %v", repented.Settles(), raised[0].Key)
	}
}

func TestAWriteIsNotJudgedWithLiveEditsSwitchedOff(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	t.Setenv(pluginData, t.TempDir())
	file := filepath.Join(root, "src", "probe.py")
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	for path, text := range map[string]string{".commandments/config.json": `{"paths": ["src"]}`, "src/probe.py": sinful} {
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

	written := func(judged bool) JournalAnswer {
		return answerWith(map[string]any{"event": "hook.PostToolUse", "project": root, "env": "main",
			"settings": map[string]any{"judge_edits": judged},
			"tool":     map[string]any{"name": "Write", "file": file}}, every, true)
	}

	if off := written(false); len(off.Raises) != 0 || off.Whisper != nil {
		t.Errorf("with live edits off the main agent's write was judged: %+v", off)
	}

	if on := written(true); len(on.Raises) != 1 {
		t.Errorf("with live edits on the sinful write raised %+v", on.Raises)
	}
}
