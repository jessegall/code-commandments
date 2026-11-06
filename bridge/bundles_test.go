package bridge

import (
	"bytes"
	"maps"
	"os"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

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
