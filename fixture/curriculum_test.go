package fixture_test

import (
	"encoding/json"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/fixture"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/skill/render"
)

// TestTheFixturesCarveTheExamplesThePhpToolCarved holds the worked examples every engine's fixture carves to the ones
// the PHP tool carved from the same fixtures, recorded in skill/render/testdata/examples.json.
func TestTheFixturesCarveTheExamplesThePhpToolCarved(t *testing.T) {
	raw, err := os.ReadFile(filepath.Join("..", "skill", "render", "testdata", "examples.json"))
	if err != nil {
		t.Fatal(err)
	}
	var want render.Examples
	if err := json.Unmarshal(raw, &want); err != nil {
		t.Fatal(err)
	}
	fixtures, err := filepath.Abs(filepath.Join("..", "tests", "Fixtures"))
	if err != nil {
		t.Fatal(err)
	}
	got, err := fixture.Curriculum(fixtures)
	if err != nil {
		t.Skip(err)
	}
	keys := map[string]bool{}
	for key := range want {
		keys[key] = true
	}
	for key, examples := range got {
		for _, example := range examples {
			keys[key] = keys[key] || example.Bad != nil || example.Good != nil
		}
	}
	var sorted []string
	for key, differs := range keys {
		if differs {
			sorted = append(sorted, key)
		}
	}
	sort.Strings(sorted)
	for _, key := range sorted {
		if wanted, gotten := describe(want[key]), describe(got[key]); wanted != gotten {
			t.Errorf("%s carves otherwise\n--- PHP\n%s\n--- Go\n%s", key, wanted, gotten)
		}
	}
}

func describe(examples []render.Example) string {
	var described []string
	for _, example := range examples {
		described = append(described, "["+string(example.Language)+"]\nBAD:\n"+half(example.Bad)+"\nGOOD:\n"+half(example.Good))
	}

	return strings.Join(described, "\n")
}

func half(text *string) string {
	if text == nil {
		return "(none)"
	}

	return *text
}

// TestAMissingBridgeStopsTheCurriculumNamingIt holds the curriculum to failing where the C# bridge is missing, naming
// the image to pull, before any fixture is read: examples carved without an engine are not ones to publish.
func TestAMissingBridgeStopsTheCurriculumNamingIt(t *testing.T) {
	t.Setenv("PATH", t.TempDir())
	_, err := fixture.Curriculum(t.TempDir())
	if err == nil || !strings.Contains(err.Error(), "docker pull "+bridge.RoslynImage()) {
		t.Errorf("without docker the curriculum answers %v", err)
	}
}
