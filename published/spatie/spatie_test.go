package spatie_test

import (
	"os"
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/engine/php"
	enginespatie "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/published"
	"github.com/jessegall/code-commandments/published/spatie"
)

func TestTheFixtureServerPublishesItsDataClassesAndTheirOutput(t *testing.T) {
	codebase, err := php.Here().Scan("../../tests/Fixtures/frontend")
	if err != nil {
		t.Skip(err)
	}
	var types []published.TypeContract
	var generated []published.GeneratedTypes
	for _, each := range (spatie.Contracts{}).Contracts(codebase) {
		switch contract := each.(type) {
		case published.TypeContract:
			types = append(types, contract)
		case published.GeneratedTypes:
			generated = append(generated, contract)
		}
	}
	names := map[string][]string{}
	for _, each := range types {
		names[each.Name] = each.Fields
	}
	if !slices.Equal(names["CustomerData"], []string{"firstName", "lastName", "emailAddress", "phoneNumber"}) || len(names) != 3 {
		t.Errorf("the published Data classes are %v", names)
	}
	output, _ := filepath.Abs("../../tests/Fixtures/frontend/generated/server-types.ts")
	if len(generated) != 1 || !strings.HasSuffix(generated[0].Location, "frontend/generated/server-types.ts") || !generated[0].Covers(evaluated(output)) {
		t.Errorf("the transformer's output is published as %v", generated)
	}
}

func evaluated(path string) string {
	real, err := filepath.EvalSymlinks(path)
	if err != nil {
		return path
	}

	return real
}

// TestTheTransformersOutputIsKnownByTheManifestItWrites holds a project whose transformer is configured where the scan
// never reads: the folder holding its manifest is its output, and only that folder.
func TestTheTransformersOutputIsKnownByTheManifestItWrites(t *testing.T) {
	root := t.TempDir()
	for path, contents := range map[string]string{
		"resources/js/types/typescript-transformer-manifest.json": `{"App/Data/index.ts": "abc"}`,
		"resources/js/types/App/Data/index.ts":                    "export type Order = { id: number, total: number };\n",
		"resources/js/pages/Order.ts":                             "export type Order = { id: number, total: number };\n",
	} {
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	codebase, err := scan.Walk([]string{root}, source.Excluded{}).Load()
	if err != nil {
		t.Skip(err)
	}

	outputs := enginespatie.TransformerOutputsBeside(codebase)
	types := evaluated(filepath.Join(root, "resources/js/types"))
	if len(outputs) != 1 || evaluated(outputs[0]) != types {
		t.Fatalf("the transformer's output is %v, want %s", outputs, types)
	}

	generated := published.GeneratedTypes{Location: outputs[0]}
	for path, covered := range map[string]bool{"resources/js/types/App/Data/index.ts": true, "resources/js/pages/Order.ts": false} {
		if got := generated.Covers(filepath.Join(filepath.Dir(outputs[0]), strings.TrimPrefix(path, "resources/js/"))); got != covered {
			t.Errorf("%s: covered %v, want %v", path, got, covered)
		}
	}
}
