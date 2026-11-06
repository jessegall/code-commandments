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

func TestEverySettingAndCardSaysItsKindInBothWords(t *testing.T) {
	for _, setting := range doc.SettingsSchema() {
		entry := setting.(*jsonfile.Object)
		key, _ := entry.Get("key")
		kind, _ := entry.Get("kind")
		written, _ := entry.Get("type")

		if kind == nil || written == nil || fmt.Sprint(kind) != fmt.Sprint(written) {
			t.Errorf("the setting %v says kind %v and type %v, not one type under both words", key, kind, written)
		}
	}

	manifest, read := jsonfile.Read(filepath.Join("..", "..", doc.Journal4Plugin))
	if !read {
		t.Fatalf("%s is not a JSON object", doc.Journal4Plugin)
	}

	cards, _ := manifest.Get("cards")
	declared, _ := cards.([]any)
	if len(declared) == 0 {
		t.Fatalf("%s declares no cards, so nothing is asked", doc.Journal4Plugin)
	}

	for _, each := range declared {
		card, _ := each.(*jsonfile.Object)
		kind, _ := card.Get("kind")
		written, _ := card.Get("type")

		if kind == nil || written == nil || fmt.Sprint(kind) != fmt.Sprint(written) {
			t.Errorf("the card %v says kind %v and type %v, not one card under both words", kind, kind, written)
		}
	}
}
