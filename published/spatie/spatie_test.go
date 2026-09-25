package spatie_test

import (
	"path/filepath"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/php"
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
