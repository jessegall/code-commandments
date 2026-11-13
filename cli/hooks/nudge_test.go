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

func marksOfThreeSins(t *testing.T) (root string, marks []SinMark) {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

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
	marks, _, _ = marksIn(Marks(root), edited, []detectors.Detector{godetectors.LoopWrappedInIfDetector{}}, git.ChangedLines{})

	if len(marks) < 3 {
		if _, err := scan.OneFile(edited).Load(); err != nil {
			t.Skipf("the Go bridge cannot run here: %v", err)
		}
		t.Fatalf("the file raises %d sins, and it holds three", len(marks))
	}

	return root, marks
}

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

func TestTheJudgeCommandIsNoLongerRepeatedUnderEveryFinding(t *testing.T) {
	root, marks := marksOfThreeSins(t)

	said := nudge(Event{Root: root}, marks)

	if strings.Contains(said, "judge ") || strings.Contains(said, "--sin=") {
		t.Errorf("the nudge still carries a judge command:\n%s", said)
	}
}

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

func TestThreeFindingsCostFarLessThanThreeBulletsDid(t *testing.T) {
	root, marks := marksOfThreeSins(t)

	said := nudge(Event{Root: root}, marks)

	const oneOldBullet = 250

	if len(said) > oneOldBullet {
		t.Errorf("three findings are said in %d characters, where one used to take about %d:\n%s", len(said), oneOldBullet, said)
	}
}

func lineOf(mark SinMark) string {
	return strings.TrimPrefix(mark.Found()[strings.LastIndex(mark.Found(), ":"):], ":")
}
