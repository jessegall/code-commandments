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
	s, _ := settled(t, elseRule)
	s.Writes(sinful(s, "a sinful file judged twice"), "twice.py")
	first := judges(s, elseRule)
	s.Exits(first, 1)
	s.Judged(1)

	again := judges(s, elseRule)

	s.Exits(again, 1)
	s.Judged(1)
	s.Remembered(1)
	s.SaysTheSameSinsAs(first, again)
}

func TestATouchedFileWithTheSameBytesIsRemembered(t *testing.T) {
	s, _ := settled(t, elseRule)
	touched := sinful(s, "a sinful file touched")
	s.Writes(touched, "touched.py")
	s.Exits(judges(s, elseRule), 1)

	s.Writes(touched, "touched.py")

	s.Exits(judges(s, elseRule), 1)
	s.Judged(1)
	s.Remembered(1)
}

func TestAMovedFileIsNotJudgedAgainAndNamedAtItsNewPath(t *testing.T) {
	s, _ := settled(t, elseRule)
	moved := sinful(s, "a sinful file moved")
	s.Writes(moved, "before.py")
	s.Exits(judges(s, elseRule), 1)

	s.Removes(moved, "before.py")
	s.Writes(moved, "after.py")
	judged := judges(s, elseRule)

	s.Judged(1)
	s.Remembered(1)
	s.Says(judged, "after.py")
	s.Never(judged, "before.py")
}

func TestTwoFilesOfTheSameBytesAreJudgedOnceAndBothNamed(t *testing.T) {
	s, _ := settled(t, elseRule)
	twin := sinful(s, "a sinful file written twice")
	s.Writes(twin, "first.py")
	s.Writes(twin, "second.py")

	judged := judges(s, elseRule)

	s.Judged(1)
	s.Remembered(1)
	s.Says(judged, "first.py", "second.py")
}

func TestAWorktreeHoldingTheSameBytesIsAnsweredFromMemory(t *testing.T) {
	s, _ := settled(t, elseRule)
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
	s.Remembered(1)
	s.Says(judged, filepath.Base(filepath.Dir(written)), "shared.py")
	s.NamesOnlyWholePaths(judged, worker.Folder())
}

func TestAnEditBackToAnEarlierStateIsRemembered(t *testing.T) {
	s, _ := settled(t, elseRule)
	earlier := sinful(s, "a sinful file edited and edited back")
	s.Writes(earlier, "back.py")
	s.Exits(judges(s, elseRule), 1)
	s.Writes(s.FreshPython("the same file edited", story.ElseAfterRaise.Text), "back.py")
	s.Exits(judges(s, elseRule), 1)

	s.Writes(earlier, "back.py")

	s.Exits(judges(s, elseRule), 1)
	s.Judged(2)
	s.Remembered(1)
}

func TestARealChangeIsJudgedOnceAndTheRestRemembered(t *testing.T) {
	s, _ := settled(t, elseRule)
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
	s.Remembered(11)
}

func TestASinIsNeverForgotten(t *testing.T) {
	s, _ := settled(t, elseRule)
	s.Writes(s.FreshPython("six elses to remember", story.SixElses.Text), "six.py")
	first := judges(s, elseRule)
	s.Says(first, "6 sins")

	again := judges(s, elseRule)

	s.Exits(again, 1)
	s.Remembered(1)
	s.Says(again, "6 sins")
	s.SaysTheSameSinsAs(first, again)
}

func writesTwelveSinfulFilesIn(s *story.Story, folder string, samples []story.Sample) {
	for at, sample := range samples {
		s.WritesIn(folder, sample, fmt.Sprintf("known%d.py", at))
	}
}

func judgesTheWorktree(s *story.Story, worker *story.Agent) story.Ran {
	return s.AsksIn(worker.Folder(), "sins", "--changes", "--sin="+elseRule)
}

func aWorkerJudgedTwelveNewFiles(t *testing.T) (*story.Story, *story.Agent, []story.Sample) {
	t.Helper()

	s := story.Begin(t)

	var grace *story.Agent
	var samples []story.Sample

	s.Given("the tree is held and a worker wrote and judged twelve new sinful files", func() {
		s.GivenTheTreeIsHeldFor(s.TestCheckout())
		grace = s.Worker("Grace")

		for one := range 12 {
			samples = append(samples, sinful(s, fmt.Sprintf("sinful file %d of twelve new ones", one)))
		}

		writesTwelveSinfulFilesIn(s, grace.Folder(), samples)
		s.Exits(judgesTheWorktree(s, grace), 1)
	})

	return s, grace, samples
}

func TestASecondWorktreeWithTheSameNewFilesParsesAndJudgesNothing(t *testing.T) {
	s, _, samples := aWorkerJudgedTwelveNewFiles(t)
	alan := s.Worker("Alan")
	writesTwelveSinfulFilesIn(s, alan.Folder(), samples)
	s.CountsFromHere()

	judged := judgesTheWorktree(s, alan)

	s.Exits(judged, 1)
	s.Says(judged, "12 sins", "known0.py")
	s.Parsed(0)
	s.Judged(0)
	s.CostAtLeast(ledger.FilesKnownByContents, 12)
}

func TestAWorktreeJudgedAgainParsesNothing(t *testing.T) {
	s, grace, _ := aWorkerJudgedTwelveNewFiles(t)
	s.CountsFromHere()

	s.Exits(judgesTheWorktree(s, grace), 1)

	s.Parsed(0)
	s.Judged(0)
}

func TestAFileChangedInAWorktreeIsTheOnlyOneParsedOrJudged(t *testing.T) {
	s, grace, _ := aWorkerJudgedTwelveNewFiles(t)
	s.WritesIn(grace.Folder(), sinful(s, "one of the twelve written anew"), "known3.py")
	s.CountsFromHere()

	s.Exits(judgesTheWorktree(s, grace), 1)

	s.Parsed(1)
	s.Judged(1)
}

func TestARuleReadingAcrossFilesIsRememberedUntilAFileMoves(t *testing.T) {
	s := held(t)
	s.Writes(s.FreshPython("a function with a copy beside it", story.SettleOriginal.Text), "payments.py")
	s.Writes(s.FreshPython("the copy of it", story.SettleCopy.Text), "refunds.py")
	first := judges(s, duplicateRule)
	s.Says(first, "payments.py", "refunds.py")
	s.CountsFromHere()

	again := judges(s, duplicateRule)

	s.SaysTheSameSinsAs(first, again)
	s.Judged(0)
	s.CostAtLeast(ledger.DetectorFilesRemembered, 1)

	s.Writes(s.FreshPython("the copy written away", story.Clean.Text), "refunds.py")
	judged := judges(s, duplicateRule)

	s.Exits(judged, 0)
	s.Never(judged, "payments.py")
	s.CostAtLeast(ledger.DetectorFilesJudged, 1)
}

func TestAWeighingRuleIsRememberedUntilAFileMoves(t *testing.T) {
	s := held(t)
	copied := story.CSharp("a c# method with a copy beside it", fmt.Sprintf(repeatedMethod, "Payments"))
	s.Writes(copied, "Payments.cs")
	s.Writes(story.CSharp("the copy of the method", fmt.Sprintf(repeatedMethod, "Refunds")), "Refunds.cs")
	first := judges(s, weighingRule)
	s.Says(first, "Payments.cs", "Refunds.cs")
	s.CountsFromHere()

	s.SaysTheSameSinsAs(first, judges(s, weighingRule))
	s.Judged(0)

	s.Writes(story.NullReturn, "Refunds.cs")
	judged := judges(s, weighingRule)

	s.Exits(judged, 0)
	s.Never(judged, "Payments.cs")
	s.CostAtLeast(ledger.DetectorFilesJudged, 1)
}

func TestAWeighingRuleIsRememberedAcrossARestart(t *testing.T) {
	s, memory, _ := settledAcrossRestarts(t)
	s.Writes(story.CSharp("a c# method with a copy beside it", fmt.Sprintf(repeatedMethod, "Payments")), "Payments.cs")
	s.Writes(story.CSharp("the copy of the method", fmt.Sprintf(repeatedMethod, "Refunds")), "Refunds.cs")
	first := judges(s, weighingRule)
	s.Says(first, "Payments.cs", "Refunds.cs")

	memory.StopsTheService()
	s.WaitsForTheService("hooks", serviceComesUp)
	s.CountsFromHere()

	s.SaysTheSameSinsAs(first, judges(s, weighingRule))
	s.Judged(0)
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
	s, _ := settled(t, elseRule, swallowRule)
	s.Writes(s.FreshPython("a file two rules both look at", story.TwoSinsInOneFile.Text), "both.py")

	s.Exits(judges(s, elseRule), 1)
	s.Exits(judges(s, swallowRule), 1)

	s.Judged(2)
	s.Remembered(0)

	s.Exits(judges(s, elseRule), 1)
	s.Exits(judges(s, swallowRule), 1)

	s.Judged(2)
	s.Remembered(2)
}
