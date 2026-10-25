package doc_test

import (
	"os"
	"regexp"
	"fmt"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/doc"
	"github.com/jessegall/code-commandments/cli/jsonfile"
	_ "github.com/jessegall/code-commandments/registry"
	_ "github.com/jessegall/code-commandments/scribes/backend"
)

// TestTheGeneratedDocumentsAreCurrent holds the README's tables and the plugin's settings to what the catalog
// generates today: a rule added or changed without generating again is missing from them.
func TestTheGeneratedDocumentsAreCurrent(t *testing.T) {
	root := filepath.Join("..", "..")
	documents, err := doc.Generated(root)
	if err != nil {
		t.Fatal(err)
	}
	for path, content := range documents {
		if current, _ := os.ReadFile(filepath.Join(root, path)); string(current) != content {
			t.Errorf("%s is stale: scripts/dev go run ./cli/doc/readme", path)
		}
	}
}

// TestEachPluginManifestNamesTheReleaseItPins holds the number a journal shows a reader to the code they have.
// Both manifests' version sat at 1.76.0 for eleven releases while the release they fetch moved on, which is how
// two consumers came to report a fault a release had already fixed.
func TestEachPluginManifestNamesTheReleaseItPins(t *testing.T) {
	root := filepath.Join("..", "..")

	for _, name := range []string{doc.Plugin, doc.Journal4Plugin} {
		text, err := os.ReadFile(filepath.Join(root, name))
		if err != nil {
			t.Fatal(err)
		}

		pinned := regexp.MustCompile(`COMMANDMENTS_RELEASE(?:=|": ")v([0-9]+\.[0-9]+\.[0-9]+)`).FindStringSubmatch(string(text))
		if pinned == nil {
			t.Fatalf("%s pins no release", name)
		}

		manifest, read := jsonfile.Read(filepath.Join(root, name))
		if !read {
			t.Fatalf("%s is not a JSON object", name)
		}

		version, _ := manifest.Get("version")
		if fmt.Sprint(version) != pinned[1] {
			t.Errorf("%s says version %v and fetches v%s: run composer sins", name, version, pinned[1])
		}
	}
}
