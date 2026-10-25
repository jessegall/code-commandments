package scribes_test

import (
	"testing"

	"github.com/jessegall/code-commandments/scribes"
)

// everything is a scope that fixes every file.
type everything struct{}

func (everything) Includes(string) bool { return true }
func (everything) IsScoped() bool       { return false }

// nothingFrozen freezes no file.
type nothingFrozen struct{}

func (nothingFrozen) IsFrozen(string) bool { return false }

// oneReported is the record of a single reported finding.
type oneReported struct{}

func (oneReported) IsFiled(rule, file string, line int) bool {
	return rule == "DictBagDetector" && file == "/src/a.py" && line == 7
}

// TestAPassRewritesWhatNobodyHasReported holds the rule every scribe runs under: the fix a scribe would apply to a
// reported finding may be the very thing the report disputes, and the rewrite would change the line's text and
// orphan the report while its issue is still open. So that one finding is left alone, and every other finding of
// the same rule, the same file and the same line under another rule is rewritten as before.
func TestAPassRewritesWhatNobodyHasReported(t *testing.T) {
	pass := scribes.Pass{Scope: everything{}, Frozen: nothingFrozen{}, Filed: oneReported{}}

	for name, test := range map[string]struct {
		rule string
		file string
		line int
		want bool
	}{
		"the reported finding itself":   {"DictBagDetector", "/src/a.py", 7, false},
		"another rule on the same line": {"MutableDefaultDetector", "/src/a.py", 7, true},
		"the same rule on another line": {"DictBagDetector", "/src/a.py", 8, true},
		"the same rule in another file": {"DictBagDetector", "/src/b.py", 7, true},
	} {
		t.Run(name, func(t *testing.T) {
			if rewrites := pass.Rewrites(test.rule, test.file, test.line); rewrites != test.want {
				t.Errorf("the pass answers %v, want %v", rewrites, test.want)
			}
		})
	}
}

// TestAPassThatKnowsOfNoReportRewritesEverything holds the cost on a project that has reported nothing: none. A
// pass with no record, and a pass told that nothing is filed, both rewrite every finding in scope.
func TestAPassThatKnowsOfNoReportRewritesEverything(t *testing.T) {
	for name, pass := range map[string]scribes.Pass{
		"no record at all":    {Scope: everything{}, Frozen: nothingFrozen{}},
		"a record of nothing": {Scope: everything{}, Frozen: nothingFrozen{}, Filed: scribes.NothingFiled{}},
	} {
		t.Run(name, func(t *testing.T) {
			if !pass.Rewrites("DictBagDetector", "/src/a.py", 7) {
				t.Error("a finding nobody reported was left alone")
			}
		})
	}
}
