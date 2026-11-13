package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestTheNewSettingsAreNotProjectConfigAndTheSchemaSaysSo(t *testing.T) {
	root, _ := workerProject(t)

	schema := string(config.Schema(root))

	for _, key := range []string{workersKey, mergesKey} {
		if strings.Contains(schema, key) {
			t.Errorf("the project's config schema declares %s, and it is a setting of the service rather than of the project: a command-line run has no worker, no merge key and no held tree", key)
		}
	}

	for _, named := range []string{"parallel", "paths", "exclude"} {
		if !strings.Contains(schema, named) {
			t.Errorf("the schema no longer declares %s, which the project's config really does carry", named)
		}
	}
}

func TestAConfigCarryingAKeyTheBinaryDoesNotKnowIsRefusedWhole(t *testing.T) {
	root, _ := workerProject(t)

	wroteInto(t, filepath.Join(root, ".commandments", "config.json"),
		`{"paths": ["src"], "judge_workers": true}`)

	_, err := config.Load(root)
	if err == nil {
		t.Fatal("a config carrying a key this binary does not know was accepted, so a typo in a rule's name would be judged as nothing")
	}

	if !strings.Contains(err.Error(), "judge_workers") {
		t.Errorf("the refusal does not name the key it did not know: %v", err)
	}
}

func TestAnOlderBinaryIgnoresTheStateFilesThisVersionAdds(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	space := workspace.At(root, "")

	if err := MergedIn(space).Judged("main", "aaaaaaa"); err != nil {
		t.Fatal(err)
	}

	if err := MergesIn(space).Keeps("main", "aaaaaaa", "what a merge brought in"); err != nil {
		t.Fatal(err)
	}

	if err := dashboard.KeepsUnjudgeable(space, []string{"a hand-in nobody could judge"}); err != nil {
		t.Fatal(err)
	}

	for _, named := range []string{".merged-judged", ".merges-found"} {
		read, err := os.ReadFile(space.Cache(named))
		if err != nil {
			t.Fatalf("%s was not written at all: %v", named, err)
		}

		if !strings.Contains(string(read), "-----") || !strings.Contains(string(read), "Safe to delete") {
			t.Errorf("%s is not in the one state format, so an older binary cannot read past it: %s", named, read)
		}

		if !strings.Contains(string(read), "Code-commandments") {
			t.Errorf("%s does not say whose file it is, so a person finding it cannot tell: %s", named, read)
		}
	}

	if _, err := config.Load(root); err != nil {
		t.Errorf("the project's own config stopped loading once the new state files existed beside it: %v", err)
	}
}
