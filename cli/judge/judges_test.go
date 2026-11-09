package judge

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
)

func TestOnlyTheRuleThatJudgesAnExcludedFolderIsReportedThere(t *testing.T) {
	root, judged, targets := excludingTests(t, `{
        "paths": ["src"],
        "exclude": ["tests"],
        "configure": {"custom/TestsDriveTheRealPath": [{"judges": ["tests"]}]}
    }`)

	findings := []engine.Finding{
		{Detector: "custom/TestsDriveTheRealPath", File: filepath.Join(root, "tests/cart_test.php"), Line: 4},
		{Detector: "php/MethodMoodDetector", File: filepath.Join(root, "tests/cart_test.php"), Line: 9},
		{Detector: "php/MethodMoodDetector", File: filepath.Join(root, "src/cart.php"), Line: 2},
	}

	kept := keep(findings, nil, targets, judged)

	if len(kept) != 2 {
		t.Fatalf("%d findings kept, want two: %v", len(kept), namesOf(kept))
	}
	for _, finding := range kept {
		if finding.Detector == "php/MethodMoodDetector" && filepath.Base(finding.File) == "cart_test.php" {
			t.Error("a rule that judges no excluded folder reported inside one")
		}
	}
}

func TestAShippedRuleNamedWithItsEngineReportsInTheFolderItJudges(t *testing.T) {
	root, judged, targets := excludingTests(t, `{
        "paths": ["src"],
        "exclude": ["tests"],
        "configure": {"python/RedundantElseDetector": [{"judges": ["tests"]}]}
    }`)

	kept := keep([]engine.Finding{
		{Detector: "RedundantElseDetector", Engine: "python", File: filepath.Join(root, "tests/cart_test.php"), Line: 4},
		{Detector: "RedundantElseDetector", Engine: "go", File: filepath.Join(root, "tests/cart_test.php"), Line: 9},
	}, nil, targets, judged)

	if len(kept) != 1 || kept[0].Engine != "python" {
		t.Errorf("the findings kept are %v, want only the python rule's", namesOf(kept))
	}
}

func TestAnExcludedFolderNobodyJudgesReportsNothing(t *testing.T) {
	root, judged, targets := excludingTests(t, `{"paths": ["src"], "exclude": ["tests"]}`)

	kept := keep([]engine.Finding{
		{Detector: "custom/TestsDriveTheRealPath", File: filepath.Join(root, "tests/cart_test.php"), Line: 4},
	}, nil, targets, judged)

	if len(kept) != 0 {
		t.Errorf("an excluded folder nobody judges reported %v", namesOf(kept))
	}
}

func TestARunGivenAPathReadsNoJudgedFolderOutsideIt(t *testing.T) {
	root, judged, _ := excludingTests(t, `{
        "paths": ["src"],
        "exclude": ["tests"],
        "configure": {"custom/TestsDriveTheRealPath": [{"judges": ["tests"]}]}
    }`)

	given, err := sourceRoots(options{path: filepath.Join(root, "src"), pathGiven: true}, judged)
	if err != nil {
		t.Fatal(err)
	}
	if len(given) != 1 || given[0] != filepath.Join(root, "src") {
		t.Errorf("a run over src roots at %v, which is not src alone", given)
	}
}

func TestAJudgedFolderOutsideTheDeclaredPathsIsStillRead(t *testing.T) {
	root, judged, _ := excludingTests(t, `{
        "paths": ["src"],
        "exclude": ["tests"],
        "configure": {"custom/TestsDriveTheRealPath": [{"judges": ["tests"]}]}
    }`)

	roots, err := sourceRoots(options{path: root}, judged)
	if err != nil {
		t.Fatal(err)
	}

	if !slices.Contains(roots, filepath.Join(root, "tests")) {
		t.Errorf("the judged folder is no root of the run: %v", roots)
	}

	read := scan.Walk(roots, source.Under(root, judged.Excluded).Judging(judged.JudgedDespiteExclusion()))
	if files := read.GivenOf(); len(files) != 2 {
		t.Errorf("the run reads %d files, want the source and the test: %v", len(files), files)
	}
}

func excludingTests(t *testing.T, text string) (string, config.Config, scope.Scope) {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range map[string]string{
		".commandments/config.json": text,
		"src/cart.php":              "<?php\n",
		"tests/cart_test.php":       "<?php\n",
	} {
		path := filepath.Join(root, name)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	judged, err := config.Load(root)
	if err != nil {
		t.Fatal(err)
	}

	targets, err := scope.FromArgs(nil, root, workspace.At(root, ""), judged.Excluded)
	if err != nil {
		t.Fatal(err)
	}

	return root, judged, targets
}

func namesOf(findings []engine.Finding) []string {
	var said []string
	for _, finding := range findings {
		said = append(said, finding.Detector+" in "+filepath.Base(finding.File))
	}

	return said
}
