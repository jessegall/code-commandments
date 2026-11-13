package cache

import (
	"path/filepath"
	"slices"
	"testing"
)

// A stamped file serves only the build that wrote it, and each save lays its contents over what the file holds.
func TestAStampedFileServesItsOwnBuildAndASaveLaysOverWhatItHolds(t *testing.T) {
	path := filepath.Join(t.TempDir(), "kept.json")
	file := Stamped[[]string]{Path: path, Build: "v1", What: "The test's lines", Warn: func(said ...string) { t.Error(said) }}

	file.Save(func(saved []string) []string { return append(saved, "first") })
	file.Save(func(saved []string) []string { return append(saved, "second") })

	if held, read := file.Read(); !read || !slices.Equal(held, []string{"first", "second"}) {
		t.Errorf("the file holds %v, read %v, not both saves", held, read)
	}
	if _, read := (Stamped[[]string]{Path: path, Build: "v2", Warn: file.Warn}).Read(); read {
		t.Error("another build was served what this build wrote")
	}
}
