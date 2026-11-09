//go:build story

package scans

import (
	"fmt"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/story"
)

const redundantElse = "python/RedundantElseDetector"

var (
	sinful = story.ElseAfterRaise.Text
	clean  = story.Clean.Text

	sinfulElsewhere = "def label(kind):\n    if not kind:\n        raise ValueError(kind)\n    else:\n        return kind.title()\n"

	countsItsReading = []string{"COMMANDMENTS_PLAIN_PROGRESS=1"}

	skippedFolders = []string{"vendor", "dist", "node_modules", "site-packages", "__pycache__", "testdata"}
)

func held(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s
}

func judgedFolderOf(s *story.Story, inside string) story.Ran {
	return s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(inside))
}

func staysWhereItWasTold(s *story.Story, ran story.Ran) {
	s.Never(ran, "panic:", "the scan left the folders it was given")
}

func readsExactly(s *story.Story, ran story.Ran, files int) {
	if counted := s.ReadsCounted(ran); counted != files {
		s.Fails(fmt.Sprintf("the run read %d files, where the folder holds %d:\n%s", counted, files, ran.Err))
	}
}

func TestAServedJudgeNeverNamesAFileAboveItsFolder(t *testing.T) {
	s := held(t)
	inside := s.Writes(story.Python("a sinful file in the start folder", sinful), "start/inside.py")
	s.Writes(story.Python("a sinful file above the start folder", sinfulElsewhere), "above.py")

	ran := judgedFolderOf(s, inside)

	s.Says(ran, "inside.py")
	s.Never(ran, "above.py")
	staysWhereItWasTold(s, ran)
}

func TestAColdJudgeNeverNamesAFileAboveItsFolder(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a sinful file above the start folder",
		story.PlainFile{Path: "src/start/inside.py", Text: sinful},
		story.PlainFile{Path: "src/above.py", Text: sinfulElsewhere},
	)

	ran := project.Judges("src/start", "--no-checklist")

	s.Says(ran, "inside.py")
	s.Never(ran, "above.py")
	staysWhereItWasTold(s, ran)
}

func TestAServedJudgeNeverFollowsALinkedFile(t *testing.T) {
	s := held(t)
	sample := story.Python("a sinful file beside a link to another", sinful)
	inside := s.Writes(sample, "start/inside.py")
	s.Writes(story.Python("the sinful file a link points at", sinfulElsewhere), "elsewhere/real.py")
	s.Links(sample, "start/linked.py", "../elsewhere/real.py")

	ran := judgedFolderOf(s, inside)

	s.Says(ran, "inside.py")
	s.Never(ran, "linked.py", "real.py")
	staysWhereItWasTold(s, ran)
}

func TestAColdJudgeNeverFollowsALinkedFileNorCountsIt(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a link to a sinful file outside the folder",
		story.PlainFile{Path: "src/inside.py", Text: sinful},
		story.PlainFile{Path: "outside/real.py", Text: sinfulElsewhere},
		story.PlainFile{Path: "src/linked.py", Link: "../outside/real.py"},
	)

	ran := project.JudgesWith(countsItsReading, "src", "--no-checklist")

	s.Says(ran, "inside.py")
	s.Never(ran, "linked.py", "real.py")
	readsExactly(s, ran, 1)
	staysWhereItWasTold(s, ran)
}

func TestAServedJudgeNeverWalksALinkedFolder(t *testing.T) {
	s := held(t)
	sample := story.Python("a sinful file beside linked folders", sinful)
	inside := s.Writes(sample, "start/inside.py")
	s.Writes(story.Python("the sinful file a link points at", sinfulElsewhere), "elsewhere/real.py")
	s.Links(sample, "start/other", "../elsewhere")
	s.Links(sample, "start/back", "..")

	ran := judgedFolderOf(s, inside)

	s.Says(ran, "inside.py")
	s.Never(ran, "real.py")
	staysWhereItWasTold(s, ran)
}

func TestAColdJudgeNeverWalksALinkedFolderAndEnds(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a folder link and a link back to its parent",
		story.PlainFile{Path: "src/inside.py", Text: sinful},
		story.PlainFile{Path: "outside/real.py", Text: sinfulElsewhere},
		story.PlainFile{Path: "src/other", Link: "../outside"},
		story.PlainFile{Path: "src/sub/back", Link: ".."},
	)

	ran := project.JudgesWith(countsItsReading, "src", "--no-checklist")

	s.Says(ran, "inside.py")
	s.Never(ran, "real.py")
	readsExactly(s, ran, 1)
	staysWhereItWasTold(s, ran)
}

func TestAServedJudgeNeverEntersAFolderThatHoldsNoSourceOfTheProject(t *testing.T) {
	s := held(t)
	inside := s.Writes(story.Python("a sinful file beside the skipped folders", sinful), "start/inside.py")

	for _, folder := range skippedFolders {
		s.Writes(story.Python("a sinful file in "+folder, sinful), filepath.Join("start", folder, "in_"+folder+".py"))
	}

	ran := judgedFolderOf(s, inside)

	s.Says(ran, "inside.py")
	neverNamesTheSkippedFolders(s, ran)
}

func TestAColdJudgeNeverEntersAFolderThatHoldsNoSourceOfTheProject(t *testing.T) {
	s := story.Begin(t)

	files := []story.PlainFile{{Path: "src/inside.py", Text: sinful}}
	for _, folder := range skippedFolders {
		files = append(files, story.PlainFile{Path: filepath.Join("src", folder, "in_"+folder+".py"), Text: sinful})
	}

	ran := s.ScratchProject("a sinful file in every skipped folder", files...).JudgesWith(countsItsReading, "src", "--no-checklist")

	s.Says(ran, "inside.py")
	neverNamesTheSkippedFolders(s, ran)
	readsExactly(s, ran, 1)
}

func neverNamesTheSkippedFolders(s *story.Story, ran story.Ran) {
	for _, folder := range skippedFolders {
		s.Never(ran, "in_"+folder+".py")
	}

	staysWhereItWasTold(s, ran)
}

func TestABuildFolderIsSkippedOnlyBesideAPackageFile(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a build folder with and without a package file",
		story.PlainFile{Path: "src/packaged/package.json", Text: "{}\n"},
		story.PlainFile{Path: "src/packaged/build/made.py", Text: sinful},
		story.PlainFile{Path: "src/plain/build/kept.py", Text: sinful},
	)

	ran := project.Judges("src", "--no-checklist")

	s.Says(ran, "kept.py")
	s.Never(ran, "made.py")
	staysWhereItWasTold(s, ran)
}

func TestAWildcardStillAppliesInsideAFolderARuleNames(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a rule that judges tests, and generated files left out",
		story.PlainFile{Path: ".commandments/config.json", Text: fmt.Sprintf(`{"paths": ["src"], "exclude": ["tests", "**/*_generated.py"], "configure": {%q: [{"judges": ["tests"]}]}}`, redundantElse)},
		story.PlainFile{Path: "src/app.py", Text: clean},
		story.PlainFile{Path: "tests/a.py", Text: sinful},
		story.PlainFile{Path: "tests/a_generated.py", Text: sinful},
	)

	ran := project.Judges("--no-checklist")

	s.Says(ran, "a.py")
	s.Never(ran, "a_generated.py")
	staysWhereItWasTold(s, ran)
}

func TestAWildcardMatchingTheNamedFolderItselfDoesNotEmptyIt(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a wildcard that matches the folder a rule names",
		story.PlainFile{Path: ".commandments/config.json", Text: fmt.Sprintf(`{"paths": ["src"], "exclude": ["**/tests"], "configure": {%q: [{"judges": ["tests"]}]}}`, redundantElse)},
		story.PlainFile{Path: "src/app.py", Text: clean},
		story.PlainFile{Path: "tests/a.py", Text: sinful},
	)

	ran := project.Judges("--no-checklist")

	s.Says(ran, "a.py")
	staysWhereItWasTold(s, ran)
}

func TestAFolderOfManyFilesReadsExactlyThoseFiles(t *testing.T) {
	const files = 12

	s := story.Begin(t)

	held := []story.PlainFile{
		{Path: "outside/elsewhere.py", Text: sinfulElsewhere},
		{Path: "src/linked.py", Link: "../outside/elsewhere.py"},
	}
	for number := range files {
		held = append(held, story.PlainFile{Path: fmt.Sprintf("src/file_%02d.py", number), Text: clean})
	}

	ran := s.ScratchProject("a folder of many files, a link and a file outside", held...).JudgesWith(countsItsReading, "src", "--no-checklist")

	readsExactly(s, ran, files)
	staysWhereItWasTold(s, ran)
}
