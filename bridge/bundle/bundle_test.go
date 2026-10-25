package bundle

import (
	"bytes"
	"maps"
	"os"
	"path/filepath"
	"testing"
	"testing/fstest"
)

var files = map[string][]byte{"bridge.php": []byte("<?php\n"), "src/Stream.php": []byte("<?php // stream\n")}

func TestTheSameFilesAlwaysPackTheSame(t *testing.T) {
	first, err := Pack(files)
	if err != nil {
		t.Fatal(err)
	}
	second, err := Pack(maps.Clone(files))
	if err != nil {
		t.Fatal(err)
	}
	if !bytes.Equal(first, second) {
		t.Error("two packs of the same files differ")
	}
	unpacked, err := Unpack(first)
	if err != nil {
		t.Fatal(err)
	}
	if !maps.EqualFunc(unpacked, files, bytes.Equal) {
		t.Errorf("the archive unpacks to %v", unpacked)
	}
}

func TestAnArchivedBundleIsWrittenOutOnceWhole(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	archive, err := Pack(files)
	if err != nil {
		t.Fatal(err)
	}
	carried := Archived("php", archive)
	folder, err := carried.Folder()
	if err != nil {
		t.Fatal(err)
	}
	written, err := Files(os.DirFS(folder), ".")
	if err != nil {
		t.Fatal(err)
	}
	if !maps.EqualFunc(written, files, bytes.Equal) {
		t.Errorf("the folder holds %v", written)
	}
	if err := os.WriteFile(filepath.Join(folder, "bridge.php"), []byte("changed"), 0o644); err != nil {
		t.Fatal(err)
	}
	again, err := carried.Folder()
	if err != nil || again != folder {
		t.Fatalf("the second use answers %s, %v", again, err)
	}
	if kept, _ := os.ReadFile(filepath.Join(folder, "bridge.php")); string(kept) != "changed" {
		t.Error("the second use wrote the bundle out again")
	}
	if siblings, _ := os.ReadDir(filepath.Dir(folder)); len(siblings) != 1 {
		t.Errorf("the cache holds %d entries beside the bundle's folder", len(siblings)-1)
	}
}

func TestAnEmbeddedBundleIsKeyedByWhatItHolds(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	one, err := Embedded("mypy", fstest.MapFS{"mypy/tree.py": {Data: []byte("a")}}, "mypy").Folder()
	if err != nil {
		t.Fatal(err)
	}
	other, err := Embedded("mypy", fstest.MapFS{"mypy/tree.py": {Data: []byte("b")}}, "mypy").Folder()
	if err != nil {
		t.Fatal(err)
	}
	if one == other {
		t.Error("two versions of the sources share a folder")
	}
	if held, _ := os.ReadFile(filepath.Join(one, "tree.py")); string(held) != "a" {
		t.Errorf("tree.py holds %q", held)
	}
}

func TestAnArchiveNeverWritesOutsideItsFolder(t *testing.T) {
	archive, err := Pack(map[string][]byte{"../escape.php": []byte("x"), "kept.php": []byte("y")})
	if err != nil {
		t.Fatal(err)
	}
	unpacked, err := Unpack(archive)
	if err != nil {
		t.Fatal(err)
	}
	if _, held := unpacked["../escape.php"]; held || len(unpacked) != 1 {
		t.Errorf("the archive unpacks to %v", unpacked)
	}
}

// TestAFolderThatLostItsFilesIsWrittenAgain holds a bundle to what the folder HOLDS rather than to the folder
// being there. The runtime builds beside the sources, and `python -m venv <folder>/venv` creates the folder it
// needs, so a folder removed by hand came back holding a virtual environment and nothing else: pip then failed on
// a requirements file that was never written again, every run, for good.
func TestAFolderThatLostItsFilesIsWrittenAgain(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	carried := Embedded("mypy", fstest.MapFS{"mypy/tree.py": {Data: []byte("the bridge")}, "mypy/requirements.txt": {Data: []byte("mypy==1.0")}}, "mypy")
	folder, err := carried.Folder()
	if err != nil {
		t.Fatal(err)
	}

	if err := os.RemoveAll(folder); err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(folder, "venv", "bin"), 0o755); err != nil {
		t.Fatal(err)
	}

	again, err := carried.Folder()
	if err != nil {
		t.Fatalf("a folder holding only what the runtime built was not written again: %v", err)
	}
	if again != folder {
		t.Errorf("the bundle answers %s, not the folder its version lives in", again)
	}
	for name, want := range map[string]string{"tree.py": "the bridge", "requirements.txt": "mypy==1.0"} {
		if held, err := os.ReadFile(filepath.Join(folder, name)); err != nil || string(held) != want {
			t.Errorf("%s reads %q, %v", name, held, err)
		}
	}
	if _, err := os.Stat(filepath.Join(folder, "venv", "bin")); err != nil {
		t.Error("healing the folder threw away what the runtime had built in it")
	}
}
