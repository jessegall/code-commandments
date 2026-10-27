package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/detectors"
	godetectors "github.com/jessegall/code-commandments/detectors/go"
	_ "github.com/jessegall/code-commandments/registry"
)

// Every word of a nudge is read by an agent on every edit it makes, so what it says is said once. These hold that:
// the file, the skill and the sin are named once however many findings of it there are, and the judge command that
// used to be repeated under each of them is gone.

// threeSins is a Go file holding three loops whose whole body is one condition, which is one sin three times in one
// file: the case that used to repeat the path, the skill and a whole command three times over. Each condition holds
// work of more than one line, since a guard over a single statement buys nothing and the rule says so.
const threeSins = `package shop

func Posted(entries []Entry) int {
	posted := 0
	for _, entry := range entries {
		if entry.Cents != 0 {
			posted += entry.Cents
			posted++
		}
	}

	return posted
}

func Counted(entries []Entry) int {
	counted := 0
	for _, entry := range entries {
		if entry.Cents > 0 {
			counted++
			counted += entry.Cents
		}
	}

	return counted
}

func Voided(entries []Entry) int {
	voided := 0
	for _, entry := range entries {
		if entry.Void {
			voided++
			voided += entry.Cents
		}
	}

	return voided
}
`

// marksOfThreeSins are the marks of a file holding one sin three times, as a check makes them.
func marksOfThreeSins(t *testing.T) (root string, marks []SinMark) {
	t.Helper()
	root = t.TempDir()

	for name, text := range map[string]string{
		".commandments/config.json": `{"paths": ["shop"]}`,
		"go.mod":                    "module probe.example/shop\n",
		"shop/entry.go":             "package shop\n\ntype Entry struct {\n\tCents int\n\tVoid  bool\n}\n",
		"shop/ledger.go":            threeSins,
	} {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	edited := filepath.Join(root, "shop/ledger.go")
	marks = marksIn(Marks(root), edited, []detectors.Detector{godetectors.LoopWrappedInIfDetector{}}, git.ChangedLines{})

	if len(marks) < 3 {
		if _, err := scan.OneFile(edited).Load(); err != nil {
			t.Skipf("the Go bridge cannot run here: %v", err)
		}
		t.Fatalf("the file raises %d sins, and it holds three", len(marks))
	}

	return root, marks
}

// TestOneSinInOneFileIsSaidOnceHoweverManyTimesItIsFound holds the whole saving: three findings of one sin in one
// file are one line naming that file once, that sin once and its skill once, with the lines it stands on listed.
func TestOneSinInOneFileIsSaidOnceHoweverManyTimesItIsFound(t *testing.T) {
	root, marks := marksOfThreeSins(t)

	said := nudge(Event{Root: root}, marks)

	if found := strings.Count(said, "shop/ledger.go"); found != 1 {
		t.Errorf("the file is named %d times:\n%s", found, said)
	}
	if found := strings.Count(said, "commandments-go-flow"); found != 1 {
		t.Errorf("the skill is named %d times:\n%s", found, said)
	}
	if found := strings.Count(said, "go-loop-wrapped-in-if"); found != 1 {
		t.Errorf("the sin is named %d times:\n%s", found, said)
	}

	for _, mark := range marks {
		if !strings.Contains(said, lineOf(mark)) {
			t.Errorf("line %s is not among the lines it names:\n%s", lineOf(mark), said)
		}
	}
}

// TestTheJudgeCommandIsNoLongerRepeatedUnderEveryFinding holds what was dropped and why it could be: an agent told
// the file and the sin can judge that file, and the line saying how was half the length of every bullet.
func TestTheJudgeCommandIsNoLongerRepeatedUnderEveryFinding(t *testing.T) {
	root, marks := marksOfThreeSins(t)

	said := nudge(Event{Root: root}, marks)

	if strings.Contains(said, "judge ") || strings.Contains(said, "--sin=") {
		t.Errorf("the nudge still carries a judge command:\n%s", said)
	}
}

// TestTheSkillIsNamedWithEverySinRaised holds the one length that is not to be saved. A mark that says only what is
// wrong leaves the agent to guess the discipline that says how to fix it, and a guess is how a finding gets
// silenced; a compaction drops a loaded skill without saying so, which is why the discipline tells an agent to
// assume it is not loaded. So every sin raised names its skill, in the one-sin line as in the list.
func TestTheSkillIsNamedWithEverySinRaised(t *testing.T) {
	root, marks := marksOfThreeSins(t)

	for name, raised := range map[string][]SinMark{
		"one finding":    marks[:1],
		"three findings": marks,
	} {
		t.Run(name, func(t *testing.T) {
			said := nudge(Event{Root: root}, raised)

			if !strings.Contains(said, "commandments-go-flow") {
				t.Errorf("it does not name the skill that teaches the fix:\n%s", said)
			}
			if !strings.Contains(said, "at its source") {
				t.Errorf("it does not say where the fix belongs:\n%s", said)
			}
		})
	}
}

// TestThreeFindingsCostFarLessThanThreeBulletsDid measures the saving rather than asserting it: the line for three
// findings of one sin is shorter than the 250 characters ONE of the old bullets took.
func TestThreeFindingsCostFarLessThanThreeBulletsDid(t *testing.T) {
	root, marks := marksOfThreeSins(t)

	said := nudge(Event{Root: root}, marks)

	// One bullet of the old shape, measured on a real project: the path, the skill and the whole judge command,
	// about 250 characters, written once per finding.
	const oneOldBullet = 250

	if len(said) > oneOldBullet {
		t.Errorf("three findings are said in %d characters, where one used to take about %d:\n%s", len(said), oneOldBullet, said)
	}
}

// lineOf is the line a mark stands on, as the nudge writes it.
func lineOf(mark SinMark) string {
	return strings.TrimPrefix(mark.Found()[strings.LastIndex(mark.Found(), ":"):], ":")
}
