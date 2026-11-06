package hooks

import (
	"path/filepath"
	"strings"
	"testing"
)

func TestAPathIsNamedAsTheWorkerSeesItWhateverTheSeparator(t *testing.T) {
	for _, told := range []struct {
		what  string
		root  string
		file  string
		named string
	}{
		{"a posix worktree", "/project-worker-1", "/project-worker-1/src/made.py", filepath.Join("src", "made.py")},
		{"a worktree with a trailing separator", "/project-worker-1/", "/project-worker-1/src/made.py", filepath.Join("src", "made.py")},
		{"a file outside the checkout, with no git to relate them", "/project-worker-1", "/project/src/made.py", "project/src/made.py"},
		{"a file in neither", "/project-worker-1", "/elsewhere/src/made.py", "elsewhere/src/made.py"},
	} {
		if named := seenFrom(told.root, told.file); named != told.named {
			t.Errorf("%s is named %q, and the worker opens %q", told.what, named, told.named)
		}
	}
}

func TestAWindowsPathIsNeverLeftAbsoluteInAMark(t *testing.T) {
	for _, told := range []struct {
		what string
		root string
		file string
	}{
		{"a drive letter and backslashes", `C:\projects\shop-worker-1`, `C:\projects\shop-worker-1\src\made.py`},
		{"a worktree on another drive", `D:\work\shop-worker-2`, `D:\work\shop-worker-2\src\made.py`},
	} {
		named := seenFrom(told.root, told.file)

		if named == told.file {
			t.Errorf("%s is named by its whole path, which is the main checkout's spelling as often as the worker's: %s", told.what, named)
		}

		if strings.Contains(named, ":") {
			t.Errorf("%s keeps its drive letter in the mark: %s", told.what, named)
		}
	}
}
