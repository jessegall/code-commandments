package samples

import (
	"bytes"
	"encoding/json"
	"os"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

func TestAFactMarkerChecksItsValueByTypeAndItsSymbolByDeclaration(t *testing.T) {
	declarations := writtenSample(t, "contract-php-declarations", ".php", []marker.Marker{label("limit", 34, "binding", 0), label("size", 32, "binding", 0)})
	reordered := declarations
	reordered.File = withTypesReordered(t, "testdata/expected/contract-php-declarations/php.jsonl")
	fact := func(key, owner string, line, column int, operator, value string, targets ...marker.Target) marker.Marker {
		return marker.Marker{Directive: marker.Directive{Key: key, Owner: owner, Column: column, Operator: operator, Value: json.RawMessage(value), Targets: targets}, Line: line}
	}
	for _, each := range []struct {
		from   Written
		marker marker.Marker
		status Status
	}{
		{declarations, fact("fact/*/constantValue", "access", 39, 30, "=", "3"), Passed},
		{declarations, fact("fact/*/constantValue", "access", 39, 30, "=", `"3"`), Wrong},
		{declarations, fact("fact/*/constantValue", "access", 39, 30, "=", "null"), Wrong},
		{declarations, fact("fact/*/constantValue", "access", 39, 16, "=", "3"), Wrong},
		{declarations, fact("fact/*/resolvedType", "literal", 32, 24, "=", `{"kind":"keyword","name":"int"}`), Passed},
		{reordered, fact("fact/*/resolvedType", "literal", 32, 24, "=", `{"kind":"keyword","name":"int"}`), Passed},
		{declarations, fact("fact/*/resolvedType", "literal", 32, 24, "=", `{"kind":"keyword","name":"string"}`), Wrong},
		{declarations, fact("fact/*/refers", "reference", 39, 36, "->", "", marker.Target{Label: "limit"}), Passed},
		{declarations, fact("fact/*/refers", "reference", 39, 36, "->", "", marker.Target{Label: "size"}), Wrong},
		{declarations, fact("fact/function/async", "", 36, 0, "=", "false"), Invalid},
	} {
		if occurrence := CheckFact(nil, each.from, each.marker); occurrence.Status != each.status {
			t.Errorf("%s: %s, want %s", each.marker.Key, occurrence, each.status)
		}
	}
}

func withTypesReordered(t *testing.T, tree string) *contract.File {
	t.Helper()
	text, err := os.ReadFile(tree)
	if err != nil {
		t.Fatal(err)
	}
	lines := strings.SplitN(string(text), "\n", 3)
	var file map[string]any
	if err := json.Unmarshal([]byte(lines[1]), &file); err != nil {
		t.Fatal(err)
	}
	held := file["file"].(map[string]any)
	types := held["types"].([]any)
	types[0], types[3] = types[3], types[0]
	var swap func(any)
	swap = func(value any) {
		switch node := value.(type) {
		case map[string]any:
			switch node["resolvedType"] {
			case 0.0:
				node["resolvedType"] = 3
			case 3.0:
				node["resolvedType"] = 0
			}
			for _, child := range node {
				swap(child)
			}
		case []any:
			for _, child := range node {
				swap(child)
			}
		}
	}
	swap(held["root"])
	rewritten, err := json.Marshal(file)
	if err != nil {
		t.Fatal(err)
	}
	stream, err := contract.ReadAll(bytes.NewReader([]byte(lines[0] + "\n" + string(rewritten) + "\n" + lines[2])))
	if err != nil {
		t.Fatal(err)
	}

	return stream.Files[0]
}
