package engine_test

import (
	"fmt"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestAMethodOverridesTheMethodOfThatNameItsProjectSupertypeDeclares(t *testing.T) {
	root := t.TempDir()
	stream := fmt.Sprintf(`{"header": {"contract": "tree", "version": 5, "language": "php", "bridge": {"name": "probe", "version": "8.0.0"}, "roots": [%q]}}
{"file": {"path": %q, "language": "php", "errors": 0, "module": "App", "resolver": {"tool": "php-parser", "ran": true}, "root": {"id": 0, "kind": "file", "span": [0, 40, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 40, 1], "name": "App", "parts": {"statements": [{"id": 2, "kind": "type", "form": "class", "span": [0, 19, 1], "name": "Base", "symbol": "App\\Base", "parts": {"writtenMembers": [{"id": 3, "kind": "function", "form": "method", "span": [5, 15, 1], "name": "run", "symbol": "App\\Base::run"}]}}, {"id": 4, "kind": "type", "form": "class", "span": [20, 40, 2], "name": "Child", "symbol": "App\\Child", "parts": {"bases": [{"id": 5, "kind": "type-expression", "form": "named", "span": [24, 28, 2], "name": "Base", "refers": "App\\Base"}], "writtenMembers": [{"id": 6, "kind": "function", "form": "method", "span": [30, 38, 2], "name": "run", "symbol": "App\\Child::run"}, {"id": 7, "kind": "function", "form": "method", "span": [38, 40, 2], "name": "stop", "symbol": "App\\Child::stop"}]}}]}}]}}, "comments": []}}
{"trailer": {"files": 1}}
`, root, root+"/Child.php")
	codebase, err := engine.FromString(stream, nil)
	if err != nil {
		t.Fatal(err)
	}
	overrides := map[string]bool{}
	for _, function := range codebase.WhereKind("function").Get() {
		overrides[function.Node().Symbol] = function.IsOverride()
	}
	if !overrides[`App\Child::run`] || overrides[`App\Child::stop`] || overrides[`App\Base::run`] {
		t.Errorf("the overrides read %v", overrides)
	}
}
