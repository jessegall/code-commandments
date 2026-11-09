//go:build story

package memory

import (
	"fmt"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

const (
	elseRule      = "redundant-python-else"
	swallowRule   = "python-swallowed-exception"
	duplicateRule = "duplicate-python-function"
	weighingRule  = "duplicate-csharp-method"
	projectRule   = "story-call"

	ruleOverEval = `{"engine": "python", "sin": {"name": "story-call", "description": "a call a story looks for", "skill": "python/flow"}, "find": {"select": "call", "where": [{"name": "eval"}]}}
`
	ruleOverExec = `{"engine": "python", "sin": {"name": "story-call", "description": "a call a story looks for", "skill": "python/flow"}, "find": {"select": "call", "where": [{"name": "exec"}]}}
`

	callsBoth = "def run(text):\n    eval(text)\n    exec(text)\n"

	repeatedMethod = `namespace Shop;

public class %s
{
    public int Settle(int amount, int fee)
    {
        var total = amount + fee;
        var rounded = total - (total %% 5);
        var charged = rounded * 2;

        return charged + fee;
    }
}
`

	justInsideAMemory = 11*time.Hour + 59*time.Minute
	justPastAMemory   = 12*time.Hour + time.Minute
	longPastAMemory   = 13 * time.Hour
	partOfAMemory     = 8 * time.Hour
	aMemoryLasts      = 12 * time.Hour

	serviceComesUp = 90 * time.Second
)

func held(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s
}

func settled(t *testing.T, rules ...string) (*story.Story, int) {
	t.Helper()

	s := story.Begin(t)

	return s, restOfTheTree(s, rules...)
}

func settledAcrossRestarts(t *testing.T) (*story.Story, *story.MemoryFile, int) {
	t.Helper()

	s := story.Begin(t)
	t.Cleanup(func() { s.WaitsForTheService("hooks", serviceComesUp) })

	return s, s.MemoryFile(), restOfTheTree(s, elseRule)
}

func restOfTheTree(s *story.Story, rules ...string) int {
	rest := 0

	s.Given("the tree is held and the rule has read it", func() {
		s.GivenTheTreeIsHeldFor(s.TestCheckout())

		for _, rule := range rules {
			s.AsksIn(s.TestCheckout(), "sins", s.TestCheckout(), "--sin="+rule)
		}

		rest = (s.Cost(ledger.DetectorFilesJudged) + s.Cost(ledger.DetectorFilesRemembered)) / len(rules)
		s.CountsFromHere()
	})

	return rest
}

func judges(s *story.Story, rule string) story.Ran {
	return s.AsksIn(s.TestCheckout(), "sins", "--changes", "--sin="+rule)
}

func sinful(s *story.Story, label string) story.Sample {
	return s.FreshPython(label, story.ElseAfterRaise.Text)
}

func TestAFileAlreadyJudgedIsNotJudgedAgain(t *testing.T) {
	s, rest := settled(t, elseRule)
	s.Writes(sinful(s, "a sinful file judged twice"), "twice.py")
	first := judges(s, elseRule)
	s.Exits(first, 1)
	s.Judged(1)

	again := judges(s, elseRule)

	s.Exits(again, 1)
	s.Judged(1)
	s.Remembered(1 + 2*rest)
	s.SaysTheSameSinsAs(first, again)
}

func TestATouchedFileWithTheSameBytesIsRemembered(t *testing.T) {
	s, rest := settled(t, elseRule)
	touched := sinful(s, "a sinful file touched")
	s.Writes(touched, "touched.py")
	s.Exits(judges(s, elseRule), 1)

	s.Writes(touched, "touched.py")

	s.Exits(judges(s, elseRule), 1)
	s.Judged(1)
	s.Remembered(1 + 2*rest)
}

func TestAMovedFileIsNotJudgedAgainAndNamedAtItsNewPath(t *testing.T) {
	s, rest := settled(t, elseRule)
	moved := sinful(s, "a sinful file moved")
	s.Writes(moved, "before.py")
	s.Exits(judges(s, elseRule), 1)

	s.Removes(moved, "before.py")
	s.Writes(moved, "after.py")
	judged := judges(s, elseRule)

	s.Judged(1)
	s.Remembered(1 + 2*rest)
	s.Says(judged, "after.py")
	s.Never(judged, "before.py")
}

func TestTwoFilesOfTheSameBytesAreJudgedOnceAndBothNamed(t *testing.T) {
	s, rest := settled(t, elseRule)
	twin := sinful(s, "a sinful file written twice")
	s.Writes(twin, "first.py")
	s.Writes(twin, "second.py")

	judged := judges(s, elseRule)

	s.Judged(1)
	s.Remembered(1 + rest)
	s.Says(judged, "first.py", "second.py")
}

func TestAWorktreeHoldingTheSameBytesIsAnsweredFromMemory(t *testing.T) {
	s, rest := settled(t, elseRule)
	worker := s.Worker("Grace")
	s.GivenTheTreeIsHeldFor(worker.Folder())
	shared := sinful(s, "a sinful file in a project and a worktree")
	s.Writes(shared, "shared.py")
	s.Exits(judges(s, elseRule), 1)
	s.Judged(1)

	written := s.WritesIn(worker.Folder(), shared, "shared.py")
	judged := s.AsksIn(worker.Folder(), "sins", "--changes", "--sin="+elseRule)

	s.Exits(judged, 1)
	s.Judged(1)
	s.Remembered(1 + 2*rest)
	s.Says(judged, filepath.Base(filepath.Dir(written)), "shared.py")
	s.NamesOnlyWholePaths(judged, worker.Folder())
}

func TestAnEditBackToAnEarlierStateIsRemembered(t *testing.T) {
	s, rest := settled(t, elseRule)
	earlier := sinful(s, "a sinful file edited and edited back")
	s.Writes(earlier, "back.py")
	s.Exits(judges(s, elseRule), 1)
	s.Writes(s.FreshPython("the same file edited", story.ElseAfterRaise.Text), "back.py")
	s.Exits(judges(s, elseRule), 1)

	s.Writes(earlier, "back.py")

	s.Exits(judges(s, elseRule), 1)
	s.Judged(2)
	s.Remembered(1 + 3*rest)
}

func TestARealChangeIsJudgedOnceAndTheRestRemembered(t *testing.T) {
	s, rest := settled(t, elseRule)
	s.Given("twelve different sinful files, judged", func() {
		for one := 1; one <= 12; one++ {
			s.Writes(sinful(s, fmt.Sprintf("sinful file %d of twelve", one)), fmt.Sprintf("file%d.py", one))
		}

		s.Exits(judges(s, elseRule), 1)
	})
	s.Judged(12)

	s.Writes(sinful(s, "sinful file 7 changed"), "file7.py")

	s.Exits(judges(s, elseRule), 1)
	s.Judged(13)
	s.Remembered(11 + 2*rest)
}

func TestASinIsNeverForgotten(t *testing.T) {
	s, rest := settled(t, elseRule)
	s.Writes(s.FreshPython("six elses to remember", story.SixElses.Text), "six.py")
	first := judges(s, elseRule)
	s.Says(first, "6 sins")

	again := judges(s, elseRule)

	s.Exits(again, 1)
	s.Remembered(1 + 2*rest)
	s.Says(again, "6 sins")
	s.SaysTheSameSinsAs(first, again)
}

func TestARuleReadingAcrossFilesIsNeverRemembered(t *testing.T) {
	s := held(t)
	s.Writes(s.FreshPython("a function with a copy beside it", story.SettleOriginal.Text), "payments.py")
	s.Writes(s.FreshPython("the copy of it", story.SettleCopy.Text), "refunds.py")
	s.Says(judges(s, duplicateRule), "payments.py", "refunds.py")

	s.Writes(s.FreshPython("the copy written away", story.Clean.Text), "refunds.py")
	judged := judges(s, duplicateRule)

	s.Exits(judged, 0)
	s.Never(judged, "payments.py")
	s.Judged(0)
	s.Remembered(0)
}

func TestAWeighingRuleIsNeverRemembered(t *testing.T) {
	s := held(t)
	copied := story.CSharp("a c# method with a copy beside it", fmt.Sprintf(repeatedMethod, "Payments"))
	s.Writes(copied, "Payments.cs")
	s.Writes(story.CSharp("the copy of the method", fmt.Sprintf(repeatedMethod, "Refunds")), "Refunds.cs")
	s.Says(judges(s, weighingRule), "Payments.cs", "Refunds.cs")

	s.Writes(story.NullReturn, "Refunds.cs")
	judged := judges(s, weighingRule)

	s.Exits(judged, 0)
	s.Never(judged, "Payments.cs")
	s.Judged(0)
	s.Remembered(0)
}

func TestARuleChangedInItsDefinitionStartsOver(t *testing.T) {
	s, rest := settled(t, projectRule)
	s.Writes(s.FreshPython("a file with two calls a rule may look for", callsBoth), "calls.py")
	s.Says(judges(s, projectRule), "calls.py:2")
	s.Exits(judges(s, projectRule), 1)
	s.Judged(1)
	s.Remembered(1 + 2*rest)

	s.RewritesTheStoryRule(ruleOverEval, ruleOverExec)
	judged := judges(s, projectRule)

	s.Judged(2 + rest)
	s.Remembered(1 + 2*rest)
	s.Says(judged, "calls.py:3")
	s.Never(judged, "calls.py:2")
}

func TestAMemoryLastsTwelveHoursFromItsLastUse(t *testing.T) {
	s, memory, rest := settledAcrossRestarts(t)
	s.Writes(sinful(s, "a sinful file aged across restarts"), "aged.py")
	s.Exits(judges(s, elseRule), 1)
	s.Judged(1)

	memory.AgesAcrossARestart(justInsideAMemory)

	s.Exits(judges(s, elseRule), 1)
	s.Judged(1)
	s.Remembered(1 + 2*rest)

	memory.AgesAcrossARestart(justPastAMemory)

	s.Exits(judges(s, elseRule), 1)
	s.Judged(2 + rest)
	s.Remembered(1 + 2*rest)
}

func TestAHitRenewsAMemory(t *testing.T) {
	s, memory, rest := settledAcrossRestarts(t)
	s.Writes(sinful(s, "a sinful file renewed by use"), "renewed.py")
	s.Exits(judges(s, elseRule), 1)

	memory.AgesAcrossARestart(partOfAMemory)
	s.Exits(judges(s, elseRule), 1)
	memory.AgesAcrossARestart(partOfAMemory)
	s.Exits(judges(s, elseRule), 1)

	s.Judged(1)
	s.Remembered(2 + 3*rest)
}

func TestAnExpiredMemoryIsDeletedFromTheFile(t *testing.T) {
	s, memory, _ := settledAcrossRestarts(t)
	expired := sinful(s, "a sinful file left to expire")
	s.Writes(expired, "expired.py")
	s.Exits(judges(s, elseRule), 1)

	memory.AgesAcrossARestart(longPastAMemory)
	s.Removes(expired, "expired.py")
	s.Writes(sinful(s, "a sinful file judged after the expiry"), "after.py")
	s.Exits(judges(s, elseRule), 1)
	memory.StopsTheService()

	reading := memory.Reads()
	if age := time.Since(time.Unix(0, reading.Oldest)); age >= aMemoryLasts {
		s.Fails(fmt.Sprintf("the memory file still holds an entry %v old, past its twelve hours: %+v", age, reading))
	}
}

func TestRulesDoNotShareAMemory(t *testing.T) {
	s, rest := settled(t, elseRule, swallowRule)
	s.Writes(s.FreshPython("a file two rules both look at", story.TwoSinsInOneFile.Text), "both.py")

	s.Exits(judges(s, elseRule), 1)
	s.Exits(judges(s, swallowRule), 1)

	s.Judged(2)
	s.Remembered(2 * rest)

	s.Exits(judges(s, elseRule), 1)
	s.Exits(judges(s, swallowRule), 1)

	s.Judged(2)
	s.Remembered(2 + 4*rest)
}
