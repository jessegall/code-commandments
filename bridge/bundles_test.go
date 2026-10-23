package bridge

import (
	"bytes"
	"maps"
	"os"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

// TestEachArchiveHoldsItsSourcesAsTheyAre holds every carried archive to the folder it is packed from, so a change
// to a bridge's sources cannot ship without the archive the binary runs.
func TestEachArchiveHoldsItsSourcesAsTheyAre(t *testing.T) {
	for archive, folder := range map[string]string{"bundles/php.tar.gz": "php", "bundles/frontend.tar.gz": "frontend/dist"} {
		raw, err := os.ReadFile(archive)
		if err != nil {
			t.Fatal(err)
		}
		packed, err := bundle.Unpack(raw)
		if err != nil {
			t.Fatal(err)
		}
		sources, err := bundle.Files(os.DirFS(folder), ".")
		if err != nil {
			t.Fatal(err)
		}
		if !maps.EqualFunc(packed, sources, bytes.Equal) {
			t.Errorf("%s no longer holds %s as it is: go generate ./bridge", archive, folder)
		}
	}
}
