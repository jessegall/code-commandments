package hooks

import (
	"bytes"
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/contract"
)

func TestASavedTreeCatchesUpOnWhatChangedAppearedOrWasDeleted(t *testing.T) {
	folder := t.TempDir()

	path := func(name string) string { return filepath.Join(folder, name) }
	write := func(name, body string) {
		if err := os.WriteFile(path(name), []byte(body), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	write("same.py", "a = 1\n")
	write("changed.py", "a = 1\n")
	write("deleted.py", "a = 1\n")

	marks := warm.Taken([]string{path("same.py"), path("changed.py"), path("deleted.py")})

	write("changed.py", "a = 22\n")
	write("new.py", "a = 1\n")

	if err := os.Remove(path("deleted.py")); err != nil {
		t.Fatal(err)
	}

	moved := movedSince(marks, []string{path("same.py"), path("changed.py"), path("new.py")})
	slices.Sort(moved)

	if want := []string{path("changed.py"), path("deleted.py"), path("new.py")}; !slices.Equal(moved, want) {
		t.Errorf("moved since the save: %v, want %v", moved, want)
	}
}

func TestAFileTheParserGotNothingOutOfIsToldAsNotParsed(t *testing.T) {
	stream, err := contract.ReadAll(bytes.NewBufferString(`{"header":{"contract":"tree","version":4,"language":"python","bridge":{"name":"t","version":"1"},"roots":["/p"]}}
{"file":{"path":"/p/a.py","language":"python","errors":1,"comments":[],"root":{"id":0,"kind":"Module","role":"other","span":[0,1,1]}}}
{"trailer":{"files":1}}
`))
	if err != nil {
		t.Fatal(err)
	}

	if !nothingCameOutOf([]*contract.Stream{stream}) {
		t.Error("a file with an error and no tree was taken for a clean one")
	}
}
