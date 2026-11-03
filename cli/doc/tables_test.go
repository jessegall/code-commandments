package doc_test

import (
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"
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

// TestThePreCommitHookNamesEveryGeneratedDocument holds the hook's list to what the generator actually writes.
// The hook re-stages a generated file by name, so a document the list leaves out is rewritten on disk and
// committed stale: the plugin manifests were left out, and v6.39.0 went out saying it was 6.38.0 — the very
// drift the manifests' own version was derived to end.
func TestThePreCommitHookNamesEveryGeneratedDocument(t *testing.T) {
	root := filepath.Join("..", "..")
	documents, err := doc.Generated(root)
	if err != nil {
		t.Fatal(err)
	}
	hook, err := os.ReadFile(filepath.Join(root, "scripts", "hooks", "pre-commit"))
	if err != nil {
		t.Fatal(err)
	}
	generators := regexp.MustCompile(`(?s)GENERATORS=\((.*?)\n\)`).FindSubmatch(hook)
	if generators == nil {
		t.Fatal("the pre-commit hook names no GENERATORS")
	}
	for path := range documents {
		if !strings.Contains(string(generators[1]), path) {
			t.Errorf("the pre-commit hook re-stages no %s, so a commit carries it stale", path)
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
