package judge

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAFindingInAFileThatIsGoneIsNotReported(t *testing.T) {
	folder := t.TempDir()
	here := filepath.Join(folder, "order.py")

	if err := os.WriteFile(here, []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if !onDisk(here) {
		t.Error("a file that is there reads as gone")
	}

	if err := os.Remove(here); err != nil {
		t.Fatal(err)
	}

	if onDisk(here) {
		t.Error("a file that was deleted still reads as being there, so a worker would be told to fix a file it cannot open")
	}

	if onDisk(folder) {
		t.Error("a folder reads as a file a finding could name")
	}

	if !onDisk("") {
		t.Error("a finding that names no file at all is dropped, and a rule may report one about the project")
	}
}

func TestTheDiskIsAskedEachTimeRatherThanRemembered(t *testing.T) {
	folder := t.TempDir()
	path := filepath.Join(folder, "late.py")

	if onDisk(path) {
		t.Fatal("a file that was never written reads as being there")
	}

	if err := os.WriteFile(path, []byte("class Late:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if !onDisk(path) {
		t.Error("a file written after it was first asked about still reads as gone, which a remembered answer would do and a service lives long enough to be wrong about")
	}
}
