package judge

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
)

// oneFile reads only the file it reports in, so a scoped run may read its files alone.
type oneFile struct{}

func (oneFile) Sin() sins.Sin                        { return nil }
func (oneFile) Find(*engine.Codebase) []engine.Match { return nil }

// acrossFiles reads a recurrence, a caller or a twin, so the whole path has to be parsed whatever the scope.
type acrossFiles struct{}

func (acrossFiles) Sin() sins.Sin                        { return nil }
func (acrossFiles) Find(*engine.Codebase) []engine.Match { return nil }
func (acrossFiles) CrossFile()                           {}

// alsoAcrossFiles is a second such rule, so the sentence has to count them.
type alsoAcrossFiles struct{}

func (alsoAcrossFiles) Sin() sins.Sin                        { return nil }
func (alsoAcrossFiles) Find(*engine.Codebase) []engine.Match { return nil }
func (alsoAcrossFiles) CrossFile()                           {}

func TestOnlyTheRulesThatReadAcrossFilesAreNamed(t *testing.T) {
	if across := readAcrossFiles([]detectors.Detector{oneFile{}}); len(across) != 0 {
		t.Errorf("a rule that answers from its own file widened the read: %v", across)
	}

	across := readAcrossFiles([]detectors.Detector{oneFile{}, acrossFiles{}, alsoAcrossFiles{}})
	if len(across) != 2 || across[0] != catalog.Name(acrossFiles{}) {
		t.Errorf("the rules that read across files are %v", across)
	}
}

// TestAWidenedReadSaysWhatWidenedIt holds the sentence a consumer needed and did not have: watching
// "reading 107/5323" for thirteen changed files, they reported --changes as broken. The count alone cannot tell
// a correct whole-tree read from an ignored scope, so the run says what made it read the whole path.
func TestAWidenedReadSaysWhatWidenedIt(t *testing.T) {
	var one bytes.Buffer
	sayWhyTheWholeTreeIsRead([]string{"DuplicateFunctionDetector"}, 4, 13, cli.Console{Out: &one, Err: &one})

	said := one.String()
	for _, wanted := range []string{"13 files judged", "DuplicateFunctionDetector reads across files", "what is reported, not what is read"} {
		if !strings.Contains(said, wanted) {
			t.Errorf("what it said does not carry %q: %q", wanted, said)
		}
	}

	// One rule is named, never counted: "1 of the 4 rules" hides the one fact a reader can act on.
	if strings.Contains(said, "1 of the") {
		t.Errorf("the only rule that widened the read is not named: %q", said)
	}

	// Scores of them is the usual case on a full curriculum, and then the count IS the fact: no single name is
	// the cause, and naming one of seventy-six reads as if removing it would narrow the run.
	var many bytes.Buffer
	sayWhyTheWholeTreeIsRead([]string{"DuplicateFunctionDetector", "DivergentTwinsDetector", "RecurringCallDetector"}, 312, 1, cli.Console{Out: &many, Err: &many})

	said = many.String()
	for _, wanted := range []string{"1 file judged", "3 of the 312 rules read across files"} {
		if !strings.Contains(said, wanted) {
			t.Errorf("what it said does not carry %q: %q", wanted, said)
		}
	}

	if strings.Contains(said, "DuplicateFunctionDetector") {
		t.Errorf("one of several rules is named as the cause: %q", said)
	}
}
