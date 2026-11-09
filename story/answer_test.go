package story

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
)

func folderHolding(t *testing.T, file string) string {
	t.Helper()

	folder := t.TempDir()
	if err := os.MkdirAll(filepath.Dir(filepath.Join(folder, file)), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(filepath.Join(folder, file), []byte("x = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	return folder
}

func TestAWholePathPassesWhetherRelativeToTheFolderOrAbsolute(t *testing.T) {
	folder := folderHolding(t, "src/order.py")
	s, _ := storyOver(t, quiet)

	s.NamesOnlyWholePaths(Ran{Out: "src/order.py:8  redundant-python-else\n"}, folder)
	s.NamesOnlyWholePaths(Ran{Out: filepath.Join(folder, "src/order.py") + ":8  redundant-python-else\n"}, folder)
}

func TestAPathThatLostItsFirstSlashIsNamed(t *testing.T) {
	folder := folderHolding(t, "src/order.py")
	lost := filepath.Join(folder, "src/order.py")[1:] + ":8"

	if named, missing := pathNotThere("  "+lost+"  a sin\n", folder); !missing || named != lost {
		t.Errorf("a path with its first slash lost read as %q, missing %v, want %q named", named, missing, lost)
	}
}

func TestAPathOutsideTheFolderThatDoesNotExistIsNamed(t *testing.T) {
	folder := folderHolding(t, "src/order.py")

	if named, missing := pathNotThere("/nowhere/at/all.py:3  a sin\n", folder); !missing || named != "/nowhere/at/all.py:3" {
		t.Errorf("a path outside the folder that does not exist read as %q, missing %v", named, missing)
	}

	if _, missing := pathNotThere("src/order.py:1 and src/order.py:2\n", folder); missing {
		t.Error("two whole paths were taken for a lost one")
	}
}

func TestProgressIsReadInTheOrderItCameAndAnAnswerWithoutLinesHasNone(t *testing.T) {
	s, _ := storyOver(t, quiet)
	answer := Ran{Err: "· this run reads the whole tree itself\nreading 1/4\nreading 4/4\ndetector 1/2\ndetector 2/2\n"}

	want := []ProgressLine{{"reading", 1, 4}, {"reading", 4, 4}, {"detector", 1, 2}, {"detector", 2, 2}}
	if got := s.ProgressOf(answer); !slices.Equal(got, want) {
		t.Errorf("the progress read is %v, want %v", got, want)
	}

	if s.ReadsCounted(answer) != 4 || s.DetectorsCounted(answer) != 2 {
		t.Errorf("reads counted %d and detectors counted %d, want 4 and 2", s.ReadsCounted(answer), s.DetectorsCounted(answer))
	}

	if lines := s.ProgressOf(Ran{Err: "· judged against the tree this session keeps up\n"}); len(lines) != 0 {
		t.Errorf("an answer with no progress lines read as %v", lines)
	}
}

func TestJudgedAndRememberedAreTheChangeSinceTheStoryBegan(t *testing.T) {
	s, _ := storyOver(t, quiet)
	costs := ledger.Of(s.project)

	costs.Counts(ledger.DetectorFilesJudged, 9, s.project)
	costs.Counts(ledger.DetectorFilesRemembered, 4, s.project)
	s.began = costsOf(s.project)

	costs.Counts(ledger.DetectorFilesJudged, 1, s.project)
	costs.Counts(ledger.DetectorFilesRemembered, 1, s.project)

	s.Judged(1)
	s.Remembered(1)
}
