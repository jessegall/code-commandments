//go:build story

package failures

import (
	"testing"

	"github.com/jessegall/code-commandments/story"
)

var textOnly = story.Sample{Under: "bridge/mypy", Label: "a note in a text file for a run that reads no source", Text: "refunds are refused past thirty days\n"}

func held(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s
}

func TestAPathOutsideTheCheckoutOfTheSameProjectSaysSo(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	judged := s.AsksIn(s.TestCheckout(), "sins", grace.Folder()+"/README.md")

	s.Says(judged, "outside")
}

func TestARunThatReadsNoFileOfAnyHeldLanguageSaysSo(t *testing.T) {
	s := held(t)
	folder := s.Scratch(textOnly.Under)
	s.Writes(textOnly, "notes.txt")

	judged := s.AsksIn(s.TestCheckout(), "sins", folder)

	s.Never(judged, "No sins found in 1 file")
}

func TestAPartialJudgingNeverCallsAFileClean(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")
	s.DropsTheLane(s.TestCheckout())

	ada.Edits(story.Clean, "total.py")

	told := ada.Hears("total.py")

	s.Never(told, "total.py is clean")
}

func TestAnEventTheServiceHasNoHandlerForIsAnsweredCleanly(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	answered := ada.SendsAnEventNobodyKnows()

	s.Exits(answered, 0)
	s.Never(answered, "Traceback")
}

func TestAnEditNamingNoFileStillGetsAnAnswer(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	answered := ada.SendsAnEditNamingNoFile()

	s.Exits(answered, 0)
	s.Never(answered, "Traceback")
}
