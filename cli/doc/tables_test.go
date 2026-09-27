package doc_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/doc"
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
