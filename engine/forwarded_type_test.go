package engine_test

import (
	"fmt"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestACallAPackageForwardsIsTypedByTheMethodOfTheClassItReaches(t *testing.T) {
	engine.RegisterPackageFacts(engine.PackageFacts{Package: "probe/forwards", Ecosystem: engine.Composer, Versions: ">=1",
		Forwards: []engine.PackageForward{{Type: `Probe\Model`, To: `Probe\Builder`}}})
	root := t.TempDir()
	stream := fmt.Sprintf(`{"header": {"contract": "tree", "version": 5, "language": "php", "bridge": {"name": "probe", "version": "8.0.0"}, "roots": [%q]}}
{"file": {"path": %q, "language": "php", "errors": 0, "module": "App", "resolver": {"tool": "php-parser", "ran": true}, "root": {"id": 0, "kind": "file", "span": [0, 40, 1], "parts": {"modules": [{"id": 1, "kind": "module", "span": [0, 40, 1], "name": "App", "parts": {"statements": [{"id": 2, "kind": "call", "span": [0, 30, 1], "parts": {"callee": {"id": 3, "kind": "access", "form": "instance", "span": [0, 25, 1], "parts": {"subject": {"id": 4, "kind": "call", "span": [0, 20, 1], "parts": {"callee": {"id": 5, "kind": "access", "form": "static", "span": [0, 14, 1], "parts": {"subject": {"id": 6, "kind": "reference", "form": "name", "span": [0, 5, 1], "name": "Brand", "refers": "App\\Brand"}, "member": {"id": 7, "kind": "reference", "form": "name", "span": [7, 14, 1], "name": "orderBy"}}}}}, "member": {"id": 8, "kind": "reference", "form": "name", "span": [22, 25, 1], "name": "get"}}}}}, {"id": 9, "kind": "type", "form": "class", "span": [31, 40, 2], "name": "Brands", "symbol": "App\\Brands"}]}}]}}, "comments": []}}
{"program": {"symbols": [{"symbol": "Probe\\Model", "kind": "class", "name": "Model"}, {"symbol": "App\\Brand", "kind": "class", "name": "Brand", "extends": ["Probe\\Model"]}, {"symbol": "Probe\\Collection", "kind": "class", "name": "Collection"}, {"symbol": "Probe\\Builder", "kind": "class", "name": "Builder", "members": [{"symbol": "Probe\\Builder::get()", "name": "get", "kind": "method", "documented": {"text": "\\Probe\\Collection", "origin": "docblock", "kind": "named", "name": "Probe\\Collection"}}]}]}}
{"trailer": {"files": 1}}
`, root, root+"/Brands.php")
	codebase, err := engine.FromString(stream, nil)
	if err != nil {
		t.Fatal(err)
	}
	typed := map[string]string{}
	for _, call := range codebase.WhereKind("call").Get() {
		if named := call.Typed(); named != nil {
			typed[call.Child("callee").Child("member").Name()] = named.Name
		}
	}
	if typed["orderBy"] != `Probe\Builder` || typed["get"] != `Probe\Collection` {
		t.Errorf("the forwarded calls read %v", typed)
	}
}
