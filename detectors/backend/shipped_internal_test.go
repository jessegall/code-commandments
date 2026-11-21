package backend

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/rules"
)

// shipped is the shipped rule judging PHP whose sin has the name.
func shipped(t *testing.T, name string) detectors.Detector {
	t.Helper()
	for _, each := range rules.Of(catalog.PHP) {
		if each.Sin().Definition().Name == name {
			return each
		}
	}
	t.Fatalf("no shipped rule %s judges PHP", name)

	return nil
}

// phpProject is the codebase of one PHP file holding the source.
func phpProject(t *testing.T, source string) *engine.Codebase {
	t.Helper()
	path := filepath.Join(t.TempDir(), "Fixture.php")
	if err := os.WriteFile(path, []byte(source), 0o600); err != nil {
		t.Fatal(err)
	}
	stream, err := php.Here().Stream(path)
	if err != nil {
		t.Fatal(err)
	}

	return engine.Load(stream)
}
