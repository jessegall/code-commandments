//go:build story

package rules

import (
	"fmt"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/story"
)

const (
	storyRule  = "StoryCallDetector"
	projectSin = "story-call"
	ownSin     = "a-worktrees-own-rule"
	workLine   = "· work: "

	namedEval    = `{"name": "eval"}`
	namedLikeEv  = `{"nameLike": "ev*"}`
	notNamedExec = `{"not": {"name": "exec"}}`
	isUnused     = `{"unused": true}`
	unknownStep  = `{"noSuchStep": true}`

	callsBoth = "def run(text):\n    eval(text)\n    exec(text)\n"
	callsNone = "def run(text):\n    return text\n"
)

var (
	ruleOverEval = ruleOver(namedEval)
	ruleOverExec = ruleOver(`{"name": "exec"}`)
)

func ruleOver(steps ...string) string {
	return fmt.Sprintf(`{"engine": "python", "sin": {"name": "%s", "description": "a call a story looks for", "skill": "python/flow"}, "find": {"select": "call", "where": [%s]}}
`, projectSin, strings.Join(steps, ", "))
}

func tries(s *story.Story, folder string) story.Ran {
	return s.Asks("rules", "try", storyRule, folder, "--work")
}

func workOf(s *story.Story, ran story.Ran) string {
	for _, line := range strings.Split(ran.Text(), "\n") {
		if strings.Contains(line, workLine) {
			return line
		}
	}

	s.Fails("the run printed no work line:\n" + ran.Text())

	return ""
}

func calls(s *story.Story, label, file string) string {
	return filepath.Dir(s.Writes(s.FreshPython(label, callsBoth), file))
}

func TestACheapCheckSparesARuleItsProgramQuestion(t *testing.T) {
	s := story.Begin(t)
	folder := calls(s, "two calls for a rule's order", "cheap.py")

	s.RewritesTheStoryRule(ruleOverEval, ruleOver(isUnused, namedEval))
	dearFirst := workOf(s, tries(s, folder))
	s.RewritesTheStoryRule(ruleOverEval, ruleOver(namedEval, isUnused))
	cheapFirst := workOf(s, tries(s, folder))

	if dearFirst != cheapFirst {
		s.Fails("the order a rule is written in changed its work: " + dearFirst + " against " + cheapFirst)
	}
}

func TestARuleFindsTheSameInAnyOrderItsStepsAreWritten(t *testing.T) {
	s := story.Begin(t)
	folder := calls(s, "two calls for six orders", "orders.py")

	for _, order := range [][]string{
		{namedEval, namedLikeEv, notNamedExec},
		{namedEval, notNamedExec, namedLikeEv},
		{namedLikeEv, namedEval, notNamedExec},
		{namedLikeEv, notNamedExec, namedEval},
		{notNamedExec, namedEval, namedLikeEv},
		{notNamedExec, namedLikeEv, namedEval},
	} {
		s.RewritesTheStoryRule(ruleOverEval, ruleOver(order...))

		found := tries(s, folder)

		s.Says(found, "orders.py:2")
		s.Never(found, "orders.py:3")
	}
}

func TestAGroupHoldingAProgramQuestionStillAnswers(t *testing.T) {
	s := story.Begin(t)
	folder := calls(s, "two calls for a group", "groups.py")
	s.RewritesTheStoryRule(ruleOverEval, ruleOver(`{"any": [`+isUnused+`, `+namedEval+`]}`))

	found := tries(s, folder)

	s.Says(found, "groups.py:2", workLine)
}

func TestAProjectRuleJudgesAnEdit(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	ada := s.Agent("Ada")

	ada.Edits(s.FreshPython("two calls an agent edits", callsBoth), "edited.py")

	s.Names(ada.Hears(projectSin), "edited.py", projectSin)
}

func TestAWorktreesOwnRuleIsNotRead(t *testing.T) {
	s := story.Begin(t)
	worker := s.Worker("Grace")
	s.GivenTheTreeIsHeldFor(worker.Folder())
	own := strings.Replace(ruleOverExec, projectSin, ownSin, 1)
	s.WritesIn(worker.Folder(), story.Sample{Under: ".commandments/custom", Label: "a rule only a worktree holds", Text: own}, "OwnRule.json")

	worker.Edits(s.FreshPython("two calls a worker edits", callsBoth), "worked.py")

	s.Names(worker.Hears(projectSin), "worked.py", projectSin)
	worker.StaysUntold(ownSin)
}

func TestABrokenRuleIsNamedAndTheRestStillRun(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	sinful := s.Writes(story.ElseAfterRaise, "sinful.py")
	s.RewritesTheStoryRule(ruleOverEval, ruleOver(unknownStep))

	judged := s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(sinful))

	s.Says(judged, storyRule, "RedundantElseDetector")
}

func TestABrokenRuleExitsThreeWhenNothingElseIsFound(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	clean := s.Writes(s.FreshPython("a file with nothing wrong", callsNone), "clean.py")
	s.RewritesTheStoryRule(ruleOverEval, ruleOver(unknownStep))

	judged := s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(clean))

	s.Says(judged, storyRule)
	s.Exits(judged, 3)
}

func TestARuleEditTakesEffectWithoutARestart(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	ada := s.Agent("Ada")
	ada.Edits(s.FreshPython("two calls before a rule changes", callsBoth), "earlier.py")
	s.Says(ada.Hears(projectSin), "earlier.py:2")
	ada.ForgetsWhatItWasTold()

	s.RewritesTheStoryRule(ruleOverEval, ruleOverExec)
	ada.Edits(s.FreshPython("two calls after a rule changes", callsBoth), "later.py")

	s.Says(ada.Hears(projectSin), "later.py:3")
}
