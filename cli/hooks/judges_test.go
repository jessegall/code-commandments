package hooks

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/detectors"
	godetectors "github.com/jessegall/code-commandments/detectors/go"
)

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
