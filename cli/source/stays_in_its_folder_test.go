package source

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
)

func placed(t *testing.T, root string, files ...string) {
	t.Helper()

	for _, file := range files {
		os.MkdirAll(filepath.Join(root, filepath.Dir(file)), 0o755)

		if err := os.WriteFile(filepath.Join(root, file), []byte("x = 1\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
}

func walked(root string) []string {
	var got []string

	for _, file := range Sources(root, Excluded{}) {
		relative, _ := filepath.Rel(root, file)
		got = append(got, relative)
	}

	slices.Sort(got)

	return got
}

func TestTheWalkNeverFollowsASymlinkedFile(t *testing.T) {
	root, outside := t.TempDir(), t.TempDir()
	placed(t, root, "src/kept.py")
	placed(t, outside, "secret.py")

	if err := os.Symlink(filepath.Join(outside, "secret.py"), filepath.Join(root, "src/linked.py")); err != nil {
		t.Fatal(err)
	}

	if got := walked(root); !slices.Equal(got, []string{"src/kept.py"}) {
		t.Errorf("the walk read %v", got)
	}
}

func TestTheWalkNeverFollowsASymlinkedFolder(t *testing.T) {
	root, outside := t.TempDir(), t.TempDir()
	placed(t, root, "src/kept.py")
	placed(t, outside, "secret.py")

	if err := os.Symlink(outside, filepath.Join(root, "src/linked")); err != nil {
		t.Fatal(err)
	}

	if got := walked(root); !slices.Equal(got, []string{"src/kept.py"}) {
		t.Errorf("the walk read %v", got)
	}
}

func TestADistFolderIsSkippedWithNoPackageJsonBesideIt(t *testing.T) {
	root := t.TempDir()
	placed(t, root, "src/kept.py", "src/dist/bundled.py", "dist/top.py")

	if got := walked(root); !slices.Equal(got, []string{"src/kept.py"}) {
		t.Errorf("the walk read %v", got)
	}
}

func TestAFolderARuleNamesIsJudgedWhileWildcardsStillApplyInsideIt(t *testing.T) {
	root := t.TempDir()
	placed(t, root, "tests/a/kept.py", "tests/a/generated/made.py")

	excluded := Under(root, []string{"tests", "**/generated"}).Judging([]string{"tests"})

	if excluded.Covers(filepath.Join(root, "tests/a/kept.py")) {
		t.Error("a file in a folder a rule names is left out")
	}

	if !excluded.Covers(filepath.Join(root, "tests/a/generated/made.py")) {
		t.Error("a wildcard stopped applying inside a folder a rule names")
	}
}

func TestAWildcardThatMatchesTheNamedFolderItselfDoesNotEmptyIt(t *testing.T) {
	root := t.TempDir()
	placed(t, root, "tests/kept.py")

	excluded := Under(root, []string{"**/tests"}).Judging([]string{"tests"})

	if excluded.Covers(filepath.Join(root, "tests/kept.py")) {
		t.Error("a wildcard naming the folder a rule names left out everything in it")
	}
}

func TestAWildcardOverAFileKeepsItOutOfTheWalkAndTheTree(t *testing.T) {
	root := t.TempDir()
	placed(t, root, "tests/a.py", "tests/a_generated.py")

	excluded := Under(root, []string{"tests", "**/*_generated.py"}).Judging([]string{"tests"})

	if found := Sources(root, excluded); len(found) != 1 || filepath.Base(found[0]) != "a.py" {
		t.Errorf("the walk found %v, where only a.py is left", found)
	}

	if Reaches(root, filepath.Join(root, "tests/a_generated.py"), excluded) {
		t.Error("a file a wildcard leaves out was reached")
	}
}

func TestAFileOutsideTheRootIsNeverReached(t *testing.T) {
	if Reaches("/project/src", "/project/other/a.py", Excluded{}) {
		t.Error("a file above the root was reached")
	}

	if Reaches("/project/src", "/elsewhere/a.py", Excluded{}) {
		t.Error("a file outside the root was reached")
	}

	root := t.TempDir()
	placed(t, root, "src/a.py")

	if !Reaches(root, filepath.Join(root, "src/a.py"), Excluded{}) {
		t.Error("a file under the root was not reached")
	}
}
