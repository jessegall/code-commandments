package warm_test

import (
	"bytes"
	"errors"
	"io/fs"
	"maps"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"testing"
	"time"

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

	if err := base.Save(folder, "binary-1", then); err != nil {
		t.Fatal(err)
	}

	saved, err := warm.LoadSaved(folder, "binary-1", roots, then)
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

	if err := base.Save(folder, "binary-1", then); err != nil {
		t.Fatal(err)
	}

	if _, err := warm.LoadSaved(folder, "binary-2", roots, then); err == nil {
		t.Error("a tree written by another binary was trusted")
	}

	if _, err := warm.LoadSaved(folder, "binary-1", []string{"/other"}, then); err == nil {
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

	if err := patched.Save(folder, "binary-1", then); err != nil {
		t.Fatal(err)
	}

	saved, err := warm.LoadSaved(folder, "binary-1", roots, then)
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

var then = time.Date(2026, 11, 9, 8, 0, 0, 0, time.UTC)

func savedAt(t *testing.T, files map[string]string) (folder string, roots []string, paths map[string]string) {
	t.Helper()

	project := t.TempDir()
	paths = map[string]string{}
	taken := map[string]warm.Known{}

	for name, body := range files {
		paths[name] = filepath.Join(project, name)
		if err := os.WriteFile(paths[name], []byte(body), 0o644); err != nil {
			t.Fatal(err)
		}

		taken[paths[name]] = warm.Now(paths[name])
	}

	folder = filepath.Join(t.TempDir(), "tree")
	roots = []string{project}

	if err := warm.Keep().Hold(project, "main", roots, codebaseOver(t, slices.Collect(maps.Values(paths))...), taken).Save(folder, "binary-1", then); err != nil {
		t.Fatal(err)
	}

	return folder, roots, paths
}

func TestARestartedServiceFindsAnUnchangedFileStandingAndAChangedOneMoved(t *testing.T) {
	folder, roots, paths := savedAt(t, map[string]string{"same.py": "a = 1\n", "touched.py": "a = 1\n", "changed.py": "a = 1\n"})

	later := time.Now().Add(time.Hour)
	if err := os.Chtimes(paths["touched.py"], later, later); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(paths["changed.py"], []byte("a = 22\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	saved, err := warm.LoadSaved(folder, "binary-1", roots, then.Add(time.Hour))
	if err != nil {
		t.Fatal(err)
	}

	for name, stands := range map[string]bool{"same.py": true, "touched.py": true, "changed.py": false} {
		if got := saved.Marks[paths[name]].Stands(paths[name]); got != stands {
			t.Errorf("after the restart %s stands is %v, want %v: only a file whose bytes changed is read again", name, got, stands)
		}
	}
}

func TestATreeLastUsedMoreThanTwelveHoursAgoIsNotLoaded(t *testing.T) {
	recent, recentRoots, _ := savedAt(t, map[string]string{"a.py": "a = 1\n"})

	if _, err := warm.LoadSaved(recent, "binary-1", recentRoots, then.Add(warm.SavedLife-time.Minute)); err != nil {
		t.Fatalf("a tree used 11 hours 59 minutes ago was refused: %v", err)
	}

	folder, roots, _ := savedAt(t, map[string]string{"a.py": "a = 1\n"})

	if _, err := warm.LoadSaved(folder, "binary-1", roots, then.Add(warm.SavedLife+time.Minute)); err == nil {
		t.Error("a tree last used more than twelve hours ago was loaded")
	}
}

func TestALoadRenewsATreeForAnotherTwelveHours(t *testing.T) {
	folder, roots, _ := savedAt(t, map[string]string{"a.py": "a = 1\n"})

	loaded := then.Add(11 * time.Hour)
	if _, err := warm.LoadSaved(folder, "binary-1", roots, loaded); err != nil {
		t.Fatal(err)
	}

	if _, err := warm.LoadSaved(folder, "binary-1", roots, loaded.Add(11*time.Hour)); err != nil {
		t.Errorf("a tree used 11 hours ago was refused, so the load before it did not renew it: %v", err)
	}
}

func TestASweepLetsGoOfTreesPastTwelveHoursAndOfUnreadableOnes(t *testing.T) {
	parent := t.TempDir()
	old, fresh, corrupt := filepath.Join(parent, "old"), filepath.Join(parent, "fresh"), filepath.Join(parent, "corrupt")

	project := t.TempDir()
	tree := savedCodebase(t)

	if err := warm.Keep().Hold(project, "main", []string{project}, tree, nil).Save(old, "binary-1", then); err != nil {
		t.Fatal(err)
	}

	if err := warm.Keep().Hold(project, "main", []string{project}, tree, nil).Save(fresh, "binary-1", then.Add(10*time.Hour)); err != nil {
		t.Fatal(err)
	}

	if err := os.MkdirAll(corrupt, 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(filepath.Join(corrupt, "head.json"), []byte("{not json"), 0o644); err != nil {
		t.Fatal(err)
	}

	warm.SweepSaved(parent, then.Add(13*time.Hour))

	for folder, kept := range map[string]bool{old: false, fresh: true, corrupt: false} {
		if _, err := os.Stat(folder); (err == nil) != kept {
			t.Errorf("after the sweep %s kept is %v, want %v", filepath.Base(folder), err == nil, kept)
		}
	}
}

func TestACorruptSavedTreeIsAnErrorOtherThanNoneSaved(t *testing.T) {
	folder, roots, _ := savedAt(t, map[string]string{"a.py": "a = 1\n"})

	if err := os.WriteFile(filepath.Join(folder, "head.json"), []byte("{not json"), 0o644); err != nil {
		t.Fatal(err)
	}

	_, err := warm.LoadSaved(folder, "binary-1", roots, then)
	if err == nil || errors.Is(err, fs.ErrNotExist) {
		t.Errorf("a corrupt head read as %v, which must be an error that is not 'nothing saved' so the service can warn", err)
	}

	if _, err := warm.LoadSaved(filepath.Join(t.TempDir(), "none"), "binary-1", roots, then); !errors.Is(err, fs.ErrNotExist) {
		t.Errorf("a tree never saved read as %v, want an error that is fs.ErrNotExist", err)
	}
}

func streamOver(t *testing.T, paths ...string) *contract.Stream {
	t.Helper()

	var text bytes.Buffer
	text.WriteString(`{"header":{"contract":"tree","version":4,"language":"python","bridge":{"name":"t","version":"1"},"roots":["/p"]}}` + "\n")

	for _, path := range paths {
		text.WriteString(`{"file":{"path":"` + path + `","language":"python","errors":0,"comments":[],"root":{"id":0,"kind":"Module","role":"other","span":[0,1,1]}}}` + "\n")
	}

	text.WriteString(`{"trailer":{"files":` + strconv.Itoa(len(paths)) + `}}` + "\n")

	stream, err := contract.ReadAll(&text)
	if err != nil {
		t.Fatal(err)
	}

	return stream
}

func codebaseOver(t *testing.T, paths ...string) *engine.Codebase {
	t.Helper()

	return engine.Load(streamOver(t, paths...))
}

func TestAFileTheTreeNeverHeldIsAddedOnceWhateverNumberOfStreamsOfItsLanguageTheTreeHolds(t *testing.T) {
	held := engine.Load(streamOver(t, "/p/a.py"), streamOver(t, "/p/b.py"))
	base := warm.Keep().Hold("/p", "main", []string{"/p"}, held, nil)

	view := base.ViewFrom("/p", []*contract.Stream{streamOver(t, "/p/new.py")}, "/p", nil)

	seen := 0
	for _, file := range view.Files() {
		if file.File.Path == "/p/new.py" {
			seen++
		}
	}

	if seen != 1 {
		t.Errorf("a new file stands %d times in a view over a tree of two python streams, so a duplicate finder would pair it with itself", seen)
	}
}
