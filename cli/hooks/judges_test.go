package hooks

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/detectors"
	godetectors "github.com/jessegall/code-commandments/detectors/go"
)

// TestAFileInAJudgedFolderIsMarkedByTheRuleLetInAlone holds the half of the whitelist a project feels while it
// writes: an edit inside an excluded folder raises the marks of the rule it let in there, live, and of no other
// rule. Before this, such an edit raised nothing until somebody ran a judge over that folder by hand.
func TestAFileInAJudgedFolderIsMarkedByTheRuleLetInAlone(t *testing.T) {
	root := projectJudging(t, `{
        "paths": ["shop"],
        "exclude": ["tests"],
        "configure": {"go/RedundantElseDetector": [{"judges": ["tests"]}]}
    }`)

	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	rules := []detectors.Detector{godetectors.RedundantElseDetector{}, godetectors.LoopWrappedInIfDetector{}}

	inTests := project.RulesFor(root, filepath.Join(root, "tests/ledger_test.go"), rules)
	if len(inTests) != 1 {
		t.Fatalf("%d rules may mark a file in the judged folder, want the one let in", len(inTests))
	}
	if _, isTheOne := inTests[0].(godetectors.RedundantElseDetector); !isTheOne {
		t.Errorf("the rule marking the judged folder is %T", inTests[0])
	}

	inSource := project.RulesFor(root, filepath.Join(root, "shop/ledger.go"), rules)
	if len(inSource) != len(rules) {
		t.Errorf("%d rules may mark a file of the declared paths, want all %d", len(inSource), len(rules))
	}
}

// TestAProjectsOwnRuleIsLetInByItsBareName holds the case the whitelist was built for, and the one a lookup by
// the engine-qualified id alone would miss: a project turns its own rule on by its bare name and disables it by
// the same, so that is the name it will write a judges call under.
func TestAProjectsOwnRuleIsLetInByItsBareName(t *testing.T) {
	root := projectJudging(t, `{
        "paths": ["shop"],
        "exclude": ["tests"],
        "configure": {"RedundantElseDetector": [{"judges": ["tests"]}]}
    }`)

	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	rules := []detectors.Detector{godetectors.RedundantElseDetector{}, godetectors.LoopWrappedInIfDetector{}}

	inTests := project.RulesFor(root, filepath.Join(root, "tests/ledger_test.go"), rules)
	if len(inTests) != 1 {
		t.Fatalf("%d rules may mark the judged folder, want the one named by its bare name", len(inTests))
	}
	if _, isTheOne := inTests[0].(godetectors.RedundantElseDetector); !isTheOne {
		t.Errorf("the rule marking the judged folder is %T", inTests[0])
	}
}

// TestAFileInAnExcludedFolderNobodyJudgesIsMarkedByNoRule holds the default the allowance must not weaken.
func TestAFileInAnExcludedFolderNobodyJudgesIsMarkedByNoRule(t *testing.T) {
	root := projectJudging(t, `{"paths": ["shop"], "exclude": ["tests"]}`)

	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	if project.IsJudged(root, filepath.Join(root, "tests/ledger_test.go")) {
		t.Error("a file in an excluded folder nobody judges is looked at")
	}
}

// TestAFileInAJudgedFolderIsLookedAtAtAll holds the step before the narrowing: the hook has to read the file
// before any rule can mark it, and a judged folder is where it would otherwise stop.
func TestAFileInAJudgedFolderIsLookedAtAtAll(t *testing.T) {
	root := projectJudging(t, `{
        "paths": ["shop"],
        "exclude": ["tests"],
        "configure": {"go/RedundantElseDetector": [{"judges": ["tests"]}]}
    }`)

	project, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	if !project.IsJudged(root, filepath.Join(root, "tests/ledger_test.go")) {
		t.Error("the file of a judged folder is not looked at, so no rule can mark it")
	}
	if !project.IsJudged(root, filepath.Join(root, "shop/ledger.go")) {
		t.Error("a file of the declared paths stopped being looked at")
	}
}

// projectJudging is a Go project whose config is the text, with a source file and a test file.
func projectJudging(t *testing.T, text string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range map[string]string{
		".commandments/config.json": text,
		"go.mod":                    "module probe.example/shop\n",
		"shop/ledger.go":            "package shop\n\nfunc Posted() int { return 1 }\n",
		"tests/ledger_test.go":      "package tests\n\nfunc Posted() int { return 1 }\n",
	} {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}
