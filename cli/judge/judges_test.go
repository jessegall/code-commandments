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

// TestOnlyTheRuleThatJudgesAnExcludedFolderIsReportedThere holds the point of the whitelist. A project excludes
// its tests because the rules about naming read a test's name as a sentence, which is what a test name is, and
// one project measured 9,773 findings in 85 files that way. One rule allowed in reports there; every other rule
// stays silent, which is what the exclusion is for.
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

// TestAnExcludedFolderNobodyJudgesReportsNothing holds the default, which the allowance must not weaken: with no
// judges call anywhere, an excluded folder is as silent as it was.
func TestAnExcludedFolderNobodyJudgesReportsNothing(t *testing.T) {
	root, judged, targets := excludingTests(t, `{"paths": ["src"], "exclude": ["tests"]}`)

	kept := keep([]engine.Finding{
		{Detector: "custom/TestsDriveTheRealPath", File: filepath.Join(root, "tests/cart_test.php"), Line: 4},
	}, nil, targets, judged)

	if len(kept) != 0 {
		t.Errorf("an excluded folder nobody judges reported %v", namesOf(kept))
	}
}

// TestARunGivenAPathReadsNoJudgedFolderOutsideIt holds the limit of the allowance, and the walk is where it
// lives rather than the filter: a run told to judge one path roots there, so a folder a rule judges elsewhere is
// never even read. A whitelist says which rule may look inside an excluded folder, never that a run should widen
// past what it was asked for.
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

// TestAJudgedFolderOutsideTheDeclaredPathsIsStillRead holds the other half of the same reading, and it is the
// half the feature needs: a project names src and excludes tests, so a walk rooted at src alone would never meet
// a test file and the rule allowed in would have nothing to read.
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

// excludingTests is a project whose config is the text, with a source file and a test file, and the scope of a
// run over the whole project.
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

// namesOf are the findings as a failure reads them: the rule and the file it reported.
func namesOf(findings []engine.Finding) []string {
	var said []string
	for _, finding := range findings {
		said = append(said, finding.Detector+" in "+filepath.Base(finding.File))
	}

	return said
}
