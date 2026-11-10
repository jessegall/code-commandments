package git

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAFileGitDoesNotTrackHasEveryLineChanged(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{"kept.py": "one = 1\n"})

	if err := os.WriteFile(filepath.Join(root, "fresh.py"), []byte("two = 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if !ChangedLinesSince(root, "HEAD", "fresh.py").Covers(1) {
		t.Error("a file git does not track is new, and its first line was not counted as changed")
	}

	if Tracks(root, "fresh.py") || !Tracks(root, "kept.py") {
		t.Error("git tracks kept.py and not fresh.py, and Tracks said otherwise")
	}
}

func TestAGitFailureIsNotAWholeFileChanged(t *testing.T) {
	notARepository := t.TempDir()

	if err := os.WriteFile(filepath.Join(notARepository, "any.py"), []byte("one = 1\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if ChangedLinesSince(notARepository, "HEAD", "any.py").Covers(1) {
		t.Error("git could not answer, and that was read as every line of the file having changed")
	}
}
