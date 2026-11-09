//go:build story

package folders

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/story"
)

const sinsInOneFile = "1 sin across 1 skill"

const coldRead = "this run reads the whole tree itself"

var (
	insideTheFolder  = story.Python("a sinful class inside the given folder", story.ElseAfterRaise.Text)
	outsideTheFolder = story.Python("a sinful function beside the given folder", "def label(kind):\n    if not kind:\n        raise ValueError(kind)\n    else:\n        return kind.title()\n")
	spelledInside    = story.Python("a sinful class in a folder spelled four ways", story.ElseAfterRaise.Text)
	bothFolders      = story.Python("a sinful class judged with no folder given", story.ElseAfterRaise.Text)
	comparedInside   = story.Python("a sinful class judged served and cold", story.ElseAfterRaise.Text)
	twinInside       = story.Python("a function whose twin lies outside the folder", duplicated)
	twinOutside      = story.Python("the twin outside the folder", duplicated)
)

const duplicated = `def total_of(lines):
    subtotal = 0
    for line in lines:
        price = line.price * line.quantity
        if line.discount:
            price = price - line.discount
        subtotal = subtotal + price
    return round(subtotal, 2)
`

var plainFolders = []story.PlainFile{
	{Path: "src/given/inside.py", Text: story.ElseAfterRaise.Text},
	{Path: "src/other/outside.py", Text: story.ElseAfterRaise.Text},
}

func held(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s
}

func TestAJudgeGivenAFolderReportsOnlyThatFolderFromTheHeldTree(t *testing.T) {
	s := held(t)
	inside := s.Writes(insideTheFolder, "given/inside.py")
	s.Writes(outsideTheFolder, "other/outside.py")

	judged := s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(inside))

	s.Exits(judged, 1)
	s.Says(judged, "inside.py", sinsInOneFile)
	s.Never(judged, "outside.py")
	s.NamesOnlyWholePaths(judged, filepath.Dir(inside))
}

func TestAJudgeGivenAFolderReportsOnlyThatFolderFromAColdRead(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("two folders, each with a sinful class", plainFolders...)

	ran := project.Judges("src/given", "--no-checklist")

	s.Says(ran, coldRead, "inside.py")
	s.Never(ran, "outside.py")
}

func TestAJudgeGivenAFolderOfAWorktreeNamesTheWorktreeFromAColdRead(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a worktree of two folders", plainFolders...)
	worktree := project.Worktree()

	ran := worktree.Judges("src/given", "--no-checklist")

	s.Says(ran, coldRead, "inside.py")
	s.Never(ran, "outside.py", project.Folder()+"/")
}

func TestATwinOutsideTheFolderKeepsItsWholePathAndIsNoFinding(t *testing.T) {
	s := held(t)
	inside := s.Writes(twinInside, "given/lines.py")
	outside := s.Writes(twinOutside, "other/lines_copy.py")

	judged := s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(inside))

	s.Says(judged, "lines.py", outside)
	s.SaysOnce(judged, "1 sin")
}

func TestOneFolderSpelledFourWaysGivesOneAnswer(t *testing.T) {
	s := held(t)
	inside := filepath.Dir(s.Writes(spelledInside, "given/inside.py"))
	relative := strings.TrimPrefix(inside, s.TestCheckout()+"/")

	first := s.AsksIn(s.TestCheckout(), "sins", inside)

	for _, spelling := range []string{relative, "./" + relative, inside + "/"} {
		if again := s.AsksIn(s.TestCheckout(), "sins", spelling); again.Out != first.Out {
			s.Fails("the folder spelled " + spelling + " answered:\n" + again.Out + "\nwhere the whole path answered:\n" + first.Out)
		}
	}
}

func TestTheHeldTreeAndAColdReadFindTheSameSinsInAFolder(t *testing.T) {
	s := held(t)
	inside := s.Writes(comparedInside, "given/inside.py")
	served := s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(inside))

	cold := s.ScratchProject("the same folder to read cold", story.PlainFile{Path: "given/inside.py", Text: comparedInside.Text}).Judges("given", "--no-checklist")

	s.Says(served, sinsInOneFile)
	s.Says(cold, sinsInOneFile, coldRead)
}

func TestNoFolderGivenJudgesBothFolders(t *testing.T) {
	s := held(t)
	s.Writes(bothFolders, "given/inside.py")
	s.Writes(bothFolders, "other/outside.py")

	judged := s.AsksIn(s.TestCheckout(), "sins")

	s.Says(judged, "inside.py", "outside.py")
}

func TestAWorktreesFolderReportsItsOwnFileAndNotTheProjectsCopy(t *testing.T) {
	s := held(t)
	s.Writes(projectsCopy, "given/copy.py")
	grace := s.Worker("Grace")
	grace.Edits(gracesFile, "given/copy.py")
	folder := filepath.Join(s.ScratchIn(grace.Folder(), gracesFile.Under), "given")

	judged := grace.Asks("sins", folder)

	s.Says(judged, "copy.py", sinsInOneFile)
	s.Never(judged, filepath.Join(s.TestCheckout(), gracesFile.Under))
	s.NamesOnlyWholePaths(judged, folder)
}

var (
	projectsCopy = story.Python("the project's copy of the worker's file", story.ElseAfterRaiseFixed.Text)
	gracesFile   = story.Python("the worker's own sinful file", story.ElseAfterRaise.Text)
)
