//go:build story

package commands

import (
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/story"
)

const (
	sinfulFixture = "tests/Fixtures/python/shop/refunds.py"
	sinfulFolder  = "tests/Fixtures/python/shop"
)

func held(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s
}

func TestJudgingChangesReportsOnlyWhatChangedGroupedBySkill(t *testing.T) {
	s := held(t)
	s.Writes(story.ElseAfterRaise, "order.py")

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Exits(judged, 1)
	s.Says(judged, "python/flow", "order.py:", "RedundantElseDetector", "checklist written to")
}

func TestARunThatFindsNothingExitsZeroAndWritesNoChecklist(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "commandments-judge-changes")

	s.Exits(judged, 0)
	s.Says(judged, "Nothing to judge")
	s.Never(judged, "checklist written to")
}

func TestJudgingTheBranchJudgesWhatDiffersFromTheMainBranch(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", "--branch")

	s.Says(judged, "files")
}

func TestJudgingAgainstANamedBranchComparesAgainstThatBranch(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", "--branch=main")

	s.Says(judged, "files")
}

func TestAPathThatIsAFileJudgesOnlyThatFile(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFixture)

	s.Exits(judged, 1)
	s.Says(judged, "refunds.py:11", "RedundantElseDetector", "1 sin across 1 skill")
	s.Never(judged, "damage_marks.py")
}

func TestAPathThatIsAFolderJudgesOnlyItsFiles(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFolder)

	s.Says(judged, "refunds.py")
	s.Never(judged, "tests/Fixtures/php")
}

func TestTwoPathsAreRefusedNamingTheOneThatWouldNeverBeJudged(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFolder, "bridge")

	s.Says(judged, "judge takes one path", "bridge")
}

func TestAPathThatDoesNotExistIsSaidAndExitsTwo(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", "src/nowhere")

	s.Exits(judged, 2)
	s.Says(judged, "Nothing to judge at")
}

func TestASinNameNobodyHasIsSaidAndExitsTwo(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFixture, "--sin=no-such-sin")

	s.Exits(judged, 2)
	s.Says(judged, "No detector matched")
}

func TestASinNameNarrowsTheRunToThatSin(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFolder, "--sin=redundant-python-else")

	s.Says(judged, "RedundantElseDetector")
	s.Never(judged, "SwallowedExceptionDetector")
}

func TestASkillNarrowsTheRunToTheSinsThatSkillFixes(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFolder, "--skill=python-flow")

	s.Says(judged, "python/flow")
	s.Never(judged, "python/exceptions")
}

func TestListingListstEveryDetectorByItsSkillAndJudgesNothing(t *testing.T) {
	s := held(t)

	listed := s.AsksIn(s.TestCheckout(), "sins", "--list")

	s.Says(listed, "RedundantElseDetector")
	s.Never(listed, "sins across")
}

func TestExcludingAFragmentSkipsFindingsInPathsWithIt(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFolder, "--exclude=refunds")

	s.Never(judged, "refunds.py")
}

func TestOneFileRulesRunsOnlyTheRulesThatAnswerFromOneFile(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFixture, "--one-file-rules")

	s.Says(judged, "RedundantElseDetector")
}

func TestNoChecklistPrintsOnly(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFixture, "--no-checklist")

	s.Says(judged, "RedundantElseDetector")
	s.Never(judged, "checklist written to")
}

func TestAChecklistIsWrittenWhereItWasAskedFor(t *testing.T) {
	s := held(t)
	folder := s.Scratch("bridge/mypy")

	judged := s.AsksIn(s.TestCheckout(), "sins", sinfulFixture, "--checklist="+folder+"/list.md")

	s.Says(judged, folder+"/list.md")
}

func TestInfoExplainsOneSin(t *testing.T) {
	s := held(t)

	explained := s.Asks("commandments", "info", "redundant-python-else")

	s.Exits(explained, 0)
	s.Says(explained, "else")
}

func TestInfoWithNoNameSaysWhatItNeeds(t *testing.T) {
	s := held(t)

	explained := s.Asks("commandments", "info")

	s.Never(explained, "Traceback")
}

func TestRepentWithDryRunPreviewsADiffAndChangesNothing(t *testing.T) {
	s := held(t)
	path := s.Writes(story.ElseAfterRaise, "order.py")

	previewed := s.AsksIn(s.TestCheckout(), "commandments", "repent", "--dry-run", filepath.Dir(path))

	s.Exits(previewed, 0)
}

func TestRepentFixesTheRepentableSinOnceAndDoesNothingTheSecondTime(t *testing.T) {
	s := held(t)
	path := s.Writes(story.ElseAfterRaise, "order.py")

	first := s.AsksIn(s.TestCheckout(), "commandments", "repent", filepath.Dir(path))
	second := s.AsksIn(s.TestCheckout(), "commandments", "repent", filepath.Dir(path))

	s.Exits(first, 0)
	s.Exits(second, 0)
}

func TestAReportAgainstARuleTheProjectDoesNotHaveIsRefused(t *testing.T) {
	s := held(t)

	reported := s.Asks("commandments", "report", "--detector=NoSuchRule", "--reason=wrong", "--ref=src/order.py:3")

	s.Refuses(reported)
}

func TestAFollowedReportIsSetAsideFromTheActiveOnes(t *testing.T) {
	s := held(t)

	followed := s.Asks("commandments", "report", "follow")

	s.Exits(followed, 0)
}

func TestRulesWorksOnAProjectsOwnRules(t *testing.T) {
	s := held(t)

	schema := s.Asks("rules", "schema")

	s.Exits(schema, 0)
}

func TestAWorkersOwnJudgeNamesTheNewFilesItCommitted(t *testing.T) {
	s := held(t)
	grace := s.DispatchedWorker("Grace", s.Agent("Ada"))

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.HandsIn()
	judged := s.AsksIn(grace.Folder(), "commandments-judge-changes")

	s.Says(judged, "order.py", "RedundantElseDetector")
}
