package config

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

// projectsRule is a rule of the project's own: it names itself and the folders it judges, as a rule file does.
type projectsRule struct {
	name   string
	judges []string
}

func (r projectsRule) Name() string                         { return r.name }
func (r projectsRule) Judges() []string                     { return r.judges }
func (r projectsRule) Sin() sins.Sin                        { return nil }
func (r projectsRule) Find(*engine.Codebase) []engine.Match { return nil }

// packagedRule is a rule the tool ships: it declares no folders of its own, and takes them from the config.
type packagedRule struct{ name string }

func (r packagedRule) Name() string                         { return r.name }
func (r packagedRule) Sin() sins.Sin                        { return nil }
func (r packagedRule) Find(*engine.Codebase) []engine.Match { return nil }

// TestARulesOwnFileNamesTheFolderItJudges holds the case this was built for: a project writes the folder in the
// rule's own file, where no install rewrites it, and nothing is written in the config at all.
func TestARulesOwnFileNamesTheFolderItJudges(t *testing.T) {
	project := Config{Paths: []string{"src"}, Excluded: []string{"tests"}}

	judging := project.Judging([]detectors.Detector{projectsRule{name: "TestsDriveTheRealPath", judges: []string{"tests"}}})

	folders := judging.Judged(Rule{Kind: Detector, Name: "TestsDriveTheRealPath"})
	if len(folders) != 1 || folders[0] != "tests" {
		t.Errorf("the rule judges %v, want the folder its own file names", folders)
	}
	if read := judging.JudgedDespiteExclusion(); len(read) != 1 || read[0] != "tests" {
		t.Errorf("the scan reads %v, want the folder the rule names", read)
	}
}

// TestAFolderNamedInBothTheRuleAndTheConfigCountsOnce holds the two sources being one answer rather than two.
func TestAFolderNamedInBothTheRuleAndTheConfigCountsOnce(t *testing.T) {
	project := Config{
		Paths:    []string{"src"},
		Excluded: []string{"tests"},
		Configurators: []Configurator{{
			Target: Rule{Kind: Detector, Name: "TestsDriveTheRealPath"},
			Calls:  []Call{{Method: Judges, Args: []Arg{{Value: "tests"}}}},
		}},
	}

	judging := project.Judging([]detectors.Detector{projectsRule{name: "TestsDriveTheRealPath", judges: []string{"tests", "spec"}}})

	folders := judging.Judged(Rule{Kind: Detector, Name: "TestsDriveTheRealPath"})
	if len(folders) != 2 {
		t.Fatalf("the rule judges %v, want tests once and spec once", folders)
	}
	if read := judging.JudgedDespiteExclusion(); len(read) != 2 {
		t.Errorf("the scan reads %v, want each folder once", read)
	}
}

// TestARuleThatNamesNoFolderChangesNothing holds that a shipped rule, which declares none, leaves the config as it
// was: the folders a shipped rule judges are named in the config, which is the only place a consumer can change
// them.
func TestARuleThatNamesNoFolderChangesNothing(t *testing.T) {
	project := Config{Paths: []string{"src"}, Excluded: []string{"tests"}}

	judging := project.Judging([]detectors.Detector{
		packagedRule{name: "php/FacadeCallDetector"},
		projectsRule{name: "NoRawSql"},
	})

	if read := judging.JudgedDespiteExclusion(); len(read) != 0 {
		t.Errorf("the scan reads %v, want nothing: no rule named a folder", read)
	}
}

// TestOnlyTheRuleThatNamesTheFolderMarksAFileInIt holds the narrowing the hook asks for, from the rule's own
// declaration rather than the config's.
func TestOnlyTheRuleThatNamesTheFolderMarksAFileInIt(t *testing.T) {
	project := Config{Paths: []string{"src"}, Excluded: []string{"tests"}}
	letIn := projectsRule{name: "TestsDriveTheRealPath", judges: []string{"tests"}}
	other := projectsRule{name: "NoRawSql"}

	judging := project.Judging([]detectors.Detector{letIn, other})
	rules := []detectors.Detector{letIn, other}

	inTests := judging.RulesFor("/project", "/project/tests/cart_test.py", rules)
	if len(inTests) != 1 {
		t.Fatalf("%d rules may mark a file in the folder, want the one that names it", len(inTests))
	}
	if inTests[0].(projectsRule).name != letIn.name {
		t.Errorf("the rule marking the folder is %s", inTests[0].(projectsRule).name)
	}

	inSource := judging.RulesFor("/project", "/project/src/cart.py", rules)
	if len(inSource) != len(rules) {
		t.Errorf("%d rules may mark a file of the declared paths, want all %d", len(inSource), len(rules))
	}
}
