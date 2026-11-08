package warm_test

import (
	"bytes"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/warm"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func savedCodebase(t *testing.T) *engine.Codebase {
	t.Helper()

	stream, err := contract.ReadAll(bytes.NewBufferString(`{"header":{"contract":"tree","version":4,"language":"python","bridge":{"name":"t","version":"1"},"roots":["/p"]}}
{"file":{"path":"/p/a.py","language":"python","errors":0,"comments":[],"root":{"id":0,"kind":"Module","role":"other","span":[0,1,1]}}}
{"trailer":{"files":1}}
`))
	if err != nil {
		t.Fatal(err)
	}

	return engine.Load(stream)
}

func TestATreeSavedByThisBinaryLoadsWithItsMarks(t *testing.T) {
	folder := filepath.Join(t.TempDir(), "tree")
	roots := []string{"/p"}
	base := warm.Keep().Hold("/p", "main", roots, savedCodebase(t), map[string]warm.Known{"/p/a.py": {Mark: "m", Digest: "d"}})

	if err := base.Save(folder, "binary-1"); err != nil {
		t.Fatal(err)
	}

	saved, err := warm.LoadSaved(folder, "binary-1", roots)
	if err != nil {
		t.Fatalf("a tree this binary saved did not load: %v", err)
	}

	if len(saved.Streams) != 1 || saved.Streams[0].Files[0].Path != "/p/a.py" {
		t.Errorf("the loaded tree holds %+v", saved.Streams)
	}

	if saved.Marks["/p/a.py"].Digest != "d" {
		t.Errorf("the loaded marks are %+v", saved.Marks)
	}
}

func TestATreeSavedByAnotherBinaryOrOverOtherRootsIsNotTrusted(t *testing.T) {
	folder := filepath.Join(t.TempDir(), "tree")
	roots := []string{"/p"}
	base := warm.Keep().Hold("/p", "main", roots, savedCodebase(t), nil)

	if err := base.Save(folder, "binary-1"); err != nil {
		t.Fatal(err)
	}

	if _, err := warm.LoadSaved(folder, "binary-2", roots); err == nil {
		t.Error("a tree written by another binary was trusted")
	}

	if _, err := warm.LoadSaved(folder, "binary-1", []string{"/other"}); err == nil {
		t.Error("a tree over other roots was trusted")
	}
}

func TestATreeSavedAfterItCaughtUpStillLoads(t *testing.T) {
	folder := filepath.Join(t.TempDir(), "tree")
	roots := []string{"/p"}
	keeper := warm.Keep()
	base := keeper.Hold("/p", "main", roots, savedCodebase(t), map[string]warm.Known{"/p/a.py": {Mark: "m", Digest: "d"}})

	added, err := contract.ReadAll(bytes.NewBufferString(`{"header":{"contract":"tree","version":4,"language":"python","bridge":{"name":"t","version":"1"},"roots":["/p"]}}
{"file":{"path":"/p/b.py","language":"python","errors":0,"comments":[],"root":{"id":0,"kind":"Module","role":"other","span":[0,1,1]}}}
{"trailer":{"files":1}}
`))
	if err != nil {
		t.Fatal(err)
	}

	view := base.ViewFrom("/p", []*contract.Stream{added}, "/p", nil)
	patched := keeper.Patched(base, view, map[string]warm.Known{"/p/b.py": {Mark: "n", Digest: "e"}, "/p/deleted.py": {}})

	if err := patched.Save(folder, "binary-1"); err != nil {
		t.Fatal(err)
	}

	saved, err := warm.LoadSaved(folder, "binary-1", roots)
	if err != nil {
		t.Fatalf("a tree saved after it caught up did not load: %v", err)
	}

	if _, kept := saved.Marks["/p/deleted.py"]; kept {
		t.Error("a deleted file is still marked in the saved tree, so every restart would catch up on it again")
	}

	files := 0
	for _, stream := range saved.Streams {
		files += len(stream.Files)
	}

	if files != 2 {
		t.Errorf("the loaded tree holds %d files, want both", files)
	}
}
