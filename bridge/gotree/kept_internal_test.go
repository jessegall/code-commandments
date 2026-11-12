package gotree

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
	"testing/fstest"
)

func TestAChangedBridgeIsAnotherIdentityAndATestFileIsNot(t *testing.T) {
	before := identityOf(fstest.MapFS{"gotree.go": {Data: []byte("package gotree\n")}, "kinds.go": {Data: []byte("package gotree\n")}})
	changed := identityOf(fstest.MapFS{"gotree.go": {Data: []byte("package gotree\n\nconst x = 1\n")}, "kinds.go": {Data: []byte("package gotree\n")}})
	tested := identityOf(fstest.MapFS{"gotree.go": {Data: []byte("package gotree\n")}, "kinds.go": {Data: []byte("package gotree\n")}, "kept_test.go": {Data: []byte("package gotree\n")}})

	if before == changed {
		t.Error("a bridge whose code changed keeps the identity its kept lines were written under, so it serves stale trees")
	}
	if before != tested {
		t.Error("a changed test file changed the bridge's identity, which starts every kept line over for nothing")
	}
	if bridgeIdentity == "" {
		t.Error("the bridge has no identity of its own code")
	}
}

func TestAKeptShapeIsReadInPlaceOfParsingTheFile(t *testing.T) {
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	path := filepath.Join(t.TempDir(), "a.go")
	source := []byte("package a\n\nfunc A() {}\n")
	if err := os.WriteFile(path, source, 0o644); err != nil {
		t.Fatal(err)
	}

	kept, _ := keptLinesFor()
	planted, _ := json.Marshal(shape{Skeleton: "planted"})
	if err := os.WriteFile(filepath.Join(kept.folder, "shape-"+fingerprint(source)), planted, 0o644); err != nil {
		t.Fatal(err)
	}

	if held := kept.shapeOf(path); held.Skeleton != "planted" {
		t.Errorf("the shape of a file whose shape is kept was %q: it was parsed again", held.Skeleton)
	}
}
